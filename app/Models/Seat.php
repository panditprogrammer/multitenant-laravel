<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    /** @use HasFactory<\Database\Factories\SeatFactory> */
    use HasFactory;

    protected $fillable = [
        'room_id',
        'seat_number',
        'type',
        'is_active',
    ];

    // 🔗 Seat belongs to Room
    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
