<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'created_by',
        'target_role',
        'title',
        'body',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** Scope: active platform announcements visible on homepage (not expired) */
    public function scopeActivePlatform($query)
    {
        return $query
            ->whereIn('target_role', ['platform', 'all'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

