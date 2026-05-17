<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'membership_id',
        'library_id',
        'room_id',
        'seat_id',
        'user_id',
        'attended_on',
    ];

    protected $casts = [
        'attended_on' => 'date',
    ];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function library()
    {
        return $this->belongsTo(Library::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
