<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderSlaAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $stage,
        public string $alertType,
        public int $delayHours
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_sla_alert',
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'seller_id' => $this->order->seller_id,
            'seller_name' => $this->order->seller?->store_name,
            'stage' => $this->stage,
            'alert_type' => $this->alertType,
            'delay_hours' => $this->delayHours,
            'tracking_number' => $this->order->tracking_number,
            'updated_at' => optional($this->order->updated_at)->toDateTimeString(),
        ];
    }
}
