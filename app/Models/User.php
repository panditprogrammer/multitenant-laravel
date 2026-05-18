<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PermissionRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'profile_image', 'role', 'library_id', 'owner_id', 'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'razorpay_key_secret', 'razorpay_webhook_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::created(function (self $user): void {
            if (! $user->role || $user->owner_id !== null || $user->hasRole($user->role)) {
                return;
            }

            PermissionRegistry::ensureDefaultRoles();
            $user->assignRole($user->role);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'razorpay_key_id' => 'encrypted',
            'razorpay_key_secret' => 'encrypted',
            'razorpay_webhook_secret' => 'encrypted',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    // get profile image url or default image
    public function getProfileImageUrlAttribute(): string
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return 'https://placehold.co/50X50?text=No\nImage';
    }

    // Owner → Libraries
    public function libraries()
    {
        return $this->hasMany(Library::class);
    }

    // Student → belongs to Library
    public function library()
    {
        return $this->belongsTo(Library::class);
    }

    // Student → Memberships (future)
    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function ownerAccount()
    {
        return $this->belongsTo(self::class, 'owner_id');
    }

    public function managedUsers()
    {
        return $this->hasMany(self::class, 'owner_id');
    }

    public function ownerAccountId(): int
    {
        return (int) ($this->owner_id ?: $this->id);
    }

    public function isStudent(): bool
    {
        return $this->role === 'student' || $this->hasRole('student');
    }

    public function isPrimaryOwner(): bool
    {
        return $this->role === 'owner' && is_null($this->owner_id);
    }

    public function canAccessOwnerPanel(): bool
    {
        return $this->role === 'owner' && ! $this->isStudent();
    }
}
