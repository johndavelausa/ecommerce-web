<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderStatusUpdated extends Notification implements ShouldQueue
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
            'type'      => 'order_status_updated',
            'order_id'  => $this->order->id,
            'status'    => $this->order->status,
            'seller_id' => $this->order->seller_id,
            'seller_name' => $this->order->seller?->store_name,
            'action_url' => $notifiable instanceof \App\Models\User && $notifiable->hasRole('seller') 
                ? route('seller.orders') 
                : route('customer.orders', ['trackingOrderId' => $this->order->id]),
            'updated_at'=> optional($this->order->updated_at)->toDateTimeString(),
        ];
    }
}

