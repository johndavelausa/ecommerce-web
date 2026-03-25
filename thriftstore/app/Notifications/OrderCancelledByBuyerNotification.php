<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCancelledByBuyerNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_cancelled_by_buyer',
            'title' => 'Order Cancelled by Buyer',
            'message' => "Order #{$this->order->id} was cancelled by the buyer.",
            'order_id' => $this->order->id,
            'action_url' => route('seller.orders'),
            'icon' => 'times-circle',
            'color' => 'red',
        ];
    }
}
