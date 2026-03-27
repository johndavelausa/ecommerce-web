<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Seller extends Model
{
    use HasFactory;
    
    protected static function booted(): void
    {
        static::updated(function (Seller $seller) {
            foreach (['banner_path', 'logo_path'] as $field) {
                if ($seller->wasChanged($field)) {
                    $old = $seller->getOriginal($field);
                    if ($old && !str_starts_with((string) $old, 'data:')) {
                        Storage::disk('public')->delete((string) $old);
                    }
                }
            }
        });

        static::deleted(function (Seller $seller) {
            foreach (['banner_path', 'logo_path'] as $field) {
                if ($seller->$field && !str_starts_with((string) $seller->$field, 'data:')) {
                    Storage::disk('public')->delete((string) $seller->$field);
                }
            }
        });
    }

    protected $fillable = [
        'user_id',
        'store_name',
        'store_description',
        'gcash_number',
        'is_open',
        'status',
        'subscription_due_date',
        'subscription_status',
        'delivery_option',
        'delivery_fee',
        'is_verified',
        'business_hours',
        'banner_path',
        'logo_path',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'is_verified' => 'boolean',
            'subscription_due_date' => 'date',
            'delivery_fee' => 'decimal:2',
        ];
    }

    /** Delivery options (A2 - v1.3) */
    public static function deliveryOptionLabels(): array
    {
        return [
            'flat_rate'   => 'Flat rate per order (one fee for entire order)',
            'per_product' => 'Per product (set delivery fee on each product)',
        ];
    }

    /**
     * Compute delivery fee for a set of items. Items: array of ['product_id' => id, 'quantity' => qty].
     * Products must be keyed by id with seller_id, delivery_fee loaded.
     */
    public function computeDeliveryFee(array $items, $productsKeyedById): float
    {
        if ($this->delivery_option === 'free') {
            return 0.0;
        }
        if ($this->delivery_option === 'flat_rate') {
            return (float) ($this->delivery_fee ?? 0);
        }
        // per_product
        $total = 0.0;
        foreach ($items as $row) {
            $product = $productsKeyedById[$row['product_id']] ?? null;
            if ($product && $product->seller_id == $this->id) {
                $fee = (float) ($product->delivery_fee ?? 0);
                $total += $fee * (int) $row['quantity'];
            }
        }
        return $total;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function payouts()
    {
        return $this->hasMany(SellerPayout::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function notes()
    {
        return $this->hasMany(SellerNote::class)->orderByDesc('created_at');
    }

    public function activityLogs()
    {
        return $this->hasMany(SellerActivityLog::class)->orderByDesc('created_at');
    }

    public function getBannerUrlAttribute()
    {
        $path = trim((string) $this->banner_path);
        if (empty($path)) {
            return null;
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, 'data:')) {
            return $path;
        }
        return asset('storage/' . $path);
    }

    public function getLogoUrlAttribute()
    {
        $path = trim((string) $this->logo_path);
        if (empty($path)) {
            return null;
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, 'data:')) {
            return $path;
        }
        return asset('storage/' . $path);
    }
}
