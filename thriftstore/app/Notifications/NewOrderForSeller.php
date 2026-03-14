<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewOrderForSeller extends Notification implements ShouldQueue
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
            'type'         => 'new_order',
            'order_id'     => $this->order->id,
            'total_amount' => $this->order->total_amount,
            'customer_id'  => $this->order->customer_id,
            'customer_name'=> $this->order->customer?->name,
            'placed_at'    => optional($this->order->created_at)->toDateTimeString(),
        ];
    }
}

