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

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
