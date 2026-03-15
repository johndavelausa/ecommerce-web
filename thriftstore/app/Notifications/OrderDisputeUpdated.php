<?php

namespace App\Notifications;

use App\Models\OrderDispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderDisputeUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrderDispute $dispute,
        public string $event
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_dispute_updated',
            'event' => $this->event,
            'dispute_id' => $this->dispute->id,
            'order_id' => $this->dispute->order_id,
            'status' => $this->dispute->status,
            'reason_code' => $this->dispute->reason_code,
            'reason_label' => OrderDispute::REASON_CODES[$this->dispute->reason_code] ?? $this->dispute->reason_code,
            'seller_id' => $this->dispute->seller_id,
            'customer_id' => $this->dispute->customer_id,
            'updated_at' => optional($this->dispute->updated_at)->toDateTimeString(),
        ];
    }
}
