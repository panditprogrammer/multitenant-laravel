<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Domain;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;


    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'email',
        'phone',
        'whatsapp',
        'state',
        'city',
        'address',
        'google_map_link',
        'profile_image',
        'is_active',
    ];


    public static function getCustomColumns(): array
    {
        return [
            'id',
            'owner_id',
            'name',
            'slug',
            'email',
            'phone',
            'whatsapp',
            'state',
            'city',
            'address',
            'google_map_link',
            'profile_image',
            'is_active',
        ];
    }


    // get profile image url or default image
    public function getProfileImageUrlAttribute(): string
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return 'https://placehold.co/50X50?text=No\nImage';
    }

    public function getIncrementing()
    {
        return true;
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }


    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
