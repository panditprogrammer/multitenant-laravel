<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StudentMembershipPaymentController extends Controller
{
    public function storeRazorpayOrder(Request $request, Membership $membership): JsonResponse
    {
        abort_unless($request->user()?->role === 'student', 403);
        abort_unless((int) $membership->user_id === (int) $request->user()->id, 403);
        abort_if($membership->status === 'cancelled', 422, 'Cancelled memberships cannot be paid.');
        abort_if($membership->paid_at, 422, 'This membership is already paid.');

        $owner = $membership->library?->owner;
        $keyId = (string) ($owner?->razorpay_key_id ?: config('services.razorpay.key_id'));
        $keySecret = (string) ($owner?->razorpay_key_secret ?: config('services.razorpay.key_secret'));

        abort_if($keyId === '' || $keySecret === '', 422, 'Razorpay is not configured yet.');

        $amountInPaise = (int) round(((float) $membership->amount) * 100);

        abort_if($amountInPaise <= 0, 422, 'Only memberships with a positive amount can be paid online.');

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->asJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'receipt' => 'membership-' . $membership->id . '-' . now()->timestamp,
                'notes' => [
                    'membership_id' => (string) $membership->id,
                    'student_id' => (string) $membership->user_id,
                    'library_id' => (string) $membership->library_id,
                ],
            ])
            ->throw()
            ->json();

        $payment = Payment::create([
            'membership_id' => $membership->id,
            'library_id' => $membership->library_id,
            'user_id' => $membership->user_id,
            'amount' => $membership->amount,
            'currency' => $response['currency'] ?? 'INR',
            'payment_method' => 'razorpay',
            'status' => $response['status'] ?? 'created',
            'reference' => $response['receipt'] ?? null,
            'razorpay_order_id' => $response['id'] ?? null,
            'gateway_payload' => $response,
        ]);

        return response()->json([
            'key' => $keyId,
            'payment' => [
                'id' => $payment->id,
                'amount' => $amountInPaise,
                'currency' => $payment->currency,
                'name' => config('app.name'),
                'description' => 'Membership fee payment',
                'order_id' => $payment->razorpay_order_id,
                'prefill' => [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'contact' => $membership->library?->phone,
                ],
            ],
        ]);
    }
}
