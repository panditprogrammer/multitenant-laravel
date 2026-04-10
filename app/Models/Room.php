<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    /** @use HasFactory<\Database\Factories\RoomFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'floor',
        'is_active',
        'library_id',
    ];

    // 🔗 One Room → Many Seats
    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    // 🔗 One Room → One Library
    public function library()
    {
        return $this->belongsTo(Library::class);
    }
}
