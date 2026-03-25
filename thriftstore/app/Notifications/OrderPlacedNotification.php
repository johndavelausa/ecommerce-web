<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
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
            'type' => 'order_placed',
            'title' => 'Order Placed Successfully',
            'message' => "Your order #{$this->order->id} from '{$this->order->seller->store_name}' has been successfully placed and is awaiting payment confirmation.",
            'order_id' => $this->order->id,
            'action_url' => route('customer.orders'),
            'icon' => 'shopping-cart',
            'color' => 'green',
        ];
    }
}
