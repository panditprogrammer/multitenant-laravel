<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $fillable = [
        'library_id',
        'user_id',
        'seat_id',
        'shift_ids',
        'start_date',
        'end_date',
        'amount',
        'status',
    ];

    // cast value to appropriate data type 
    protected $casts = [
        'shift_ids' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
    ];


    // 🔗 Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
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
