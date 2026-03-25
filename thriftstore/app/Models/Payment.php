<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'type',
        'amount',
        'gcash_number',
        'reference_number',
        'screenshot_path',
        'status',
        'rejection_reason',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Get the full URL for the payment screenshot.
     */
    public function getScreenshotUrlAttribute()
    {
        if (!$this->screenshot_path) {
            return null;
        }

        if (str_starts_with((string) $this->screenshot_path, 'data:')) {
            return $this->screenshot_path;
        }

        return asset('storage/' . $this->screenshot_path);
    }
}
