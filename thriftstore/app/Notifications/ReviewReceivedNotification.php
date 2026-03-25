<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReviewReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(public ?Order $order = null, public ?Review $review = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->review) {
            return [
                'type' => 'product_review_received',
                'title' => 'New Product Review',
                'message' => "A customer has reviewed your product '{$this->review->product->name}'. Rating: {$this->review->rating}/5.",
                'product_id' => $this->review->product_id,
                'action_url' => route('seller.reviews'),
                'icon' => 'star',
                'color' => 'yellow',
            ];
        }

        return [
            'type' => 'store_review_received',
            'title' => 'New Store Review',
            'message' => "A customer has rated your store for Order #{$this->order->id}. Rating: {$this->order->store_rating}/5.",
            'order_id' => $this->order->id,
            'action_url' => route('seller.reviews'),
            'icon' => 'store',
            'color' => 'yellow',
        ];
    }
}
