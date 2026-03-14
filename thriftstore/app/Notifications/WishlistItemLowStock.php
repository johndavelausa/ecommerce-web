<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class WishlistItemLowStock extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Product $product, public int $stock)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'wishlist_low_stock',
            'product_id'   => $this->product->id,
            'product_name' => $this->product->name,
            'stock'        => $this->stock,
            'seller_id'    => $this->product->seller_id,
            'seller_name'  => $this->product->seller?->store_name,
        ];
    }
}

