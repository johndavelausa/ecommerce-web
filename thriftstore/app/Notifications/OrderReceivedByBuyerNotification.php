<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderReceivedByBuyerNotification extends Notification
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
            'type' => 'order_received_by_buyer',
            'title' => 'Order Received by Buyer',
            'message' => "Order #{$this->order->id} has been marked as received by the buyer.",
            'order_id' => $this->order->id,
            'action_url' => route('seller.orders'),
            'icon' => 'check-circle',
            'color' => 'green',
        ];
    }
}
