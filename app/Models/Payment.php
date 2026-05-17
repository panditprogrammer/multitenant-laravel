<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'membership_id',
        'library_id',
        'user_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'reference',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'gateway_payload',
        'paid_at',
        'verified_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_payload' => 'array',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', ['paid', 'captured']);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function library()
    {
        return $this->belongsTo(Library::class);
    }
}
