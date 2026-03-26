<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class RefundStatusUpdated extends Notification implements ShouldQueue
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
        $statusLabel = Order::refundStatusLabel($this->order->refund_status);
        $reasonLabel = Order::refundReasonLabel($this->order->refund_reason_code);
        
        $message = "Your refund for Order #{$this->order->id} is {$statusLabel}.";
        if ($this->order->refund_status === Order::REFUND_STATUS_PENDING) {
            $message = "A refund for Order #{$this->order->id} has been initiated and is now being processed.";
        } elseif ($this->order->refund_status === Order::REFUND_STATUS_COMPLETED) {
            $message = "Great news! Your refund for Order #{$this->order->id} has been completed.";
        }

        return [
            'type'           => 'refund_status_updated',
            'order_id'       => $this->order->id,
            'refund_status'  => $this->order->refund_status,
            'refund_reason'  => $reasonLabel,
            'message'        => $message,
            'action_url'     => route('customer.orders', ['trackingOrderId' => $this->order->id]),
            'seller_name'    => $this->order->seller?->store_name,
            'updated_at'     => optional($this->order->updated_at)->toDateTimeString(),
        ];
    }
}
