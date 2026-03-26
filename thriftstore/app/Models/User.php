<?php

namespace App\Models;


use App\Models\Seller;
use App\Models\Order;
use App\Models\Review;
use App\Models\Address;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;
    
    protected static function booted(): void
    {
        static::updated(function (User $user) {
            if ($user->wasChanged('avatar')) {
                $old = $user->getOriginal('avatar');
                if ($old && !str_starts_with((string) $old, 'data:')) {
                    Storage::disk('public')->delete((string) $old);
                }
            }
        });

        static::deleted(function (User $user) {
            if ($user->avatar && !str_starts_with((string) $user->avatar, 'data:')) {
                Storage::disk('public')->delete((string) $user->avatar);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'contact_number',
        'address',
        'avatar',
        'last_active_at',
        'is_suspicious',
        'suspicious_reason',
        'suspicious_flagged_at',
        'email_verified_at',
        'pending_email',  // B1: seller email change — new email until verified
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'last_active_at' => 'datetime',
            'is_suspicious' => 'boolean',
            'suspicious_flagged_at' => 'datetime',
        ];
    }

    public function seller()
    {
        return $this->hasOne(Seller::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function getAvatarUrlAttribute()
    {
        $path = trim((string) $this->avatar);
        if (!$path) return null;
        if (str_starts_with($path, 'data:')) return $path;
        return asset('storage/' . $path);
    }
}
