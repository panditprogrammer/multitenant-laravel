<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{

    protected $fillable = [
        'user_id',
        'library_id',
        'seat_id',
        'start_date',
        'end_date',
        'plan_type',
        'amount',
    ];


    // cast value to appropriate data type 
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }

    public function library()
    {
        return $this->belongsTo(Library::class);
    }

    public function shifts()
{
    return $this->belongsToMany(Shift::class, 'membership_shift');
}
}
