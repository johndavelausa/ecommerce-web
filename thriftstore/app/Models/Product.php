<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Models\Wishlist;
use App\Notifications\WishlistItemLowStock;

class Product extends Model
{
    use HasFactory;

    /** D4 v1.4 — Invalidate public listing cache when any product is added, updated, or deleted. */
    protected static function booted(): void
    {
        static::created(fn () => self::invalidateListingCache());
        static::updated(fn () => self::invalidateListingCache());
        static::deleted(fn () => self::invalidateListingCache());
    }

    public static function invalidateListingCache(): void
    {
        Cache::put('products.listing.version', (int) Cache::get('products.listing.version', 0) + 1, 86400);
    }

    protected $fillable = [
        'seller_id',
        'name',
        'description',
        'category',
        'tags',
        'price',
        'sale_price',
        'stock',
        'image_path',
        'is_active',
        'condition',
        'delivery_fee',
        'views',
        'low_stock_threshold',
    ];

    /** Condition options for thrift/ukay items (A1 - v1.3) */
    public static function conditionOptions(): array
    {
        return [
            'new'       => 'New',
            'like_new'  => 'Like New',
            'good'      => 'Good',
        ];
    }

    /** Human-readable condition label */
    public function getConditionLabelAttribute(): string
    {
        $key = $this->condition ?? 'good';
        return self::conditionOptions()[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'is_active' => 'boolean',
            'views' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function histories()
    {
        return $this->hasMany(ProductHistory::class);
    }

    public function productReports()
    {
        return $this->hasMany(ProductReport::class);
    }

    /**
     * Notify customers who have this product on their wishlist if stock has just dropped to low_stock_threshold or below.
     */
    public function notifyWishlistLowStockIfNeeded(int $oldStock, int $newStock): void
    {
        $threshold = (int) ($this->low_stock_threshold ?? 10);
        if ($oldStock > $threshold && $newStock > 0 && $newStock <= $threshold) {
            $wishlists = Wishlist::query()
                ->with('customer')
                ->where('product_id', $this->id)
                ->get();

            foreach ($wishlists as $wishlist) {
                $customer = $wishlist->customer;
                if ($customer) {
                    $customer->notify(new WishlistItemLowStock($this, $newStock));
                }
            }
        }
    }
}
