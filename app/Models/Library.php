<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Library extends Model
{

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'email',
        'password',
        'phone',
        'whatsapp',
        'state',
        'city',
        'address',
        'google_map_link',
        'profile_image',
        'is_active',
        'open_time',
        'close_time',
        'normal_price',
        'ac_price',
    ];

    // cast values to appropriate types 
    protected $casts = [
        'is_active' => 'boolean',
        'open_time' => 'datetime:H:i',
        'close_time' => 'datetime:H:i',
        'normal_price' => 'decimal:2',
        'ac_price' => 'decimal:2',
    ];

    // delete image when library is deleted 
    protected static function booted()
    {
        static::deleting(function ($library) {
            if ($library->profile_image) {
                Storage::delete($library->profile_image);
            }
        });
    }

    // get profile image url or default image
    public function getProfileImageUrlAttribute(): string
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return 'https://placehold.co/50X50?text=No\nImage';
    }

    // Library → Owner
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Library → Rooms
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    // Library → Students
    public function students()
    {
        return $this->hasMany(User::class);
    }

    // Library → Seats (via rooms)
    public function seats()
    {
        return $this->hasManyThrough(Seat::class, Room::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }
}
