<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Payment;
use App\Services\MembershipPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, MembershipPaymentService $membershipPaymentService)
    {
        $data = $request->json()->all();
        $event = (string) data_get($data, 'event', '');
        $paymentEntity = data_get($data, 'payload.payment.entity', []);
        $orderEntity = data_get($data, 'payload.order.entity', []);
        $signature = (string) $request->header('X-Razorpay-Signature');
        $payload = $request->getContent();

        $orderId = data_get($paymentEntity, 'order_id') ?: data_get($orderEntity, 'id');
        $paymentId = data_get($paymentEntity, 'id');
        $membershipId = data_get($paymentEntity, 'notes.membership_id')
            ?: data_get($orderEntity, 'notes.membership_id');

        $payment = Payment::query()
            ->when($orderId, fn ($query) => $query->where('razorpay_order_id', $orderId))
            ->when(!$orderId && $paymentId, fn ($query) => $query->where('razorpay_payment_id', $paymentId))
            ->with('library.owner')
            ->first();

        $membership = null;

        if (!$payment && $membershipId) {
            $membership = Membership::query()
                ->with('library.owner')
                ->find($membershipId);
        }

        $secret = (string) ($payment?->library?->owner?->razorpay_webhook_secret
            ?: $membership?->library?->owner?->razorpay_webhook_secret);

        abort_if($secret === '', 500, 'Razorpay webhook secret is missing for this owner.');
        abort_unless(hash_equals(hash_hmac('sha256', $payload, $secret), $signature), 403);

        if (!$payment && $membership) {
            if ($membership) {
                $payment = Payment::create([
                    'membership_id' => $membership->id,
                    'library_id' => $membership->library_id,
                    'user_id' => $membership->user_id,
                    'amount' => ((int) (data_get($paymentEntity, 'amount') ?? data_get($orderEntity, 'amount', 0))) / 100,
                    'currency' => data_get($paymentEntity, 'currency') ?? data_get($orderEntity, 'currency', 'INR'),
                    'payment_method' => 'razorpay',
                    'status' => 'pending',
                    'reference' => data_get($orderEntity, 'receipt'),
                    'razorpay_order_id' => $orderId,
                ]);
            }
        }

        if (!$payment) {
            return response()->json(['received' => false], 404);
        }

        $status = match ($event) {
            'payment.captured', 'order.paid' => 'paid',
            'payment.authorized' => 'authorized',
            'payment.failed' => 'failed',
            default => data_get($paymentEntity, 'status', $payment->status),
        };

        $paidAt = in_array($status, ['paid', 'captured'], true)
            ? Carbon::createFromTimestamp((int) (data_get($paymentEntity, 'created_at') ?? now()->timestamp))
            : null;

        $payment->update([
            'payment_method' => 'razorpay',
            'status' => $status,
            'razorpay_order_id' => $orderId ?: $payment->razorpay_order_id,
            'razorpay_payment_id' => $paymentId ?: $payment->razorpay_payment_id,
            'razorpay_signature' => $signature,
            'reference' => $payment->reference ?: data_get($orderEntity, 'receipt'),
            'gateway_payload' => $data,
            'verified_at' => now(),
            'paid_at' => $paidAt ?: $payment->paid_at,
        ]);

        if (in_array($status, ['paid', 'captured'], true)) {
            $membershipPaymentService->syncMembership($payment->membership()->first());
        }

        return response()->json(['received' => true]);
    }
}
