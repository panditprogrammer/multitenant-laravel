<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class MembershipPaymentService
{
    public function recordCashPayment(Membership $membership, $paidAt = null): Payment
    {
        $existingPayment = $membership->payments()
            ->successful()
            ->where('payment_method', 'cash')
            ->latest('paid_at')
            ->first();

        if ($existingPayment) {
            $this->syncMembership($membership);

            return $existingPayment;
        }

        $payment = $membership->payments()->create([
            'library_id' => $membership->library_id,
            'user_id' => $membership->user_id,
            'amount' => $membership->amount,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'status' => 'paid',
            'reference' => 'CASH-' . str_pad((string) $membership->id, 6, '0', STR_PAD_LEFT),
            'paid_at' => $paidAt ?? now(),
            'verified_at' => now(),
        ]);

        $this->syncMembership($membership->fresh());

        return $payment;
    }

    public function syncMembership(Membership $membership): Membership
    {
        $successfulPayment = $membership->payments()
            ->successful()
            ->latest('paid_at')
            ->latest('id')
            ->first();

        $membership->update([
            'payment_method' => $successfulPayment?->payment_method,
            'paid_at' => $successfulPayment?->paid_at,
        ]);

        return $membership->fresh();
    }
}
