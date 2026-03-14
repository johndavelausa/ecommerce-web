<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Wishlist;
use App\Notifications\WishlistItemLowStock;

class Product extends Model
{
    use HasFactory;

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
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
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

    /**
     * Notify customers who have this product on their wishlist if stock has just dropped to a low level.
     */
    public function notifyWishlistLowStockIfNeeded(int $oldStock, int $newStock): void
    {
        // Only notify when crossing from >3 down to 1–3 items left.
        if ($oldStock > 3 && $newStock > 0 && $newStock <= 3) {
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
