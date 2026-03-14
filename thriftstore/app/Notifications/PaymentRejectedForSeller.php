<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentRejectedForSeller extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment, public string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_rejected',
            'payment_id' => $this->payment->id,
            'payment_type' => $this->payment->type,
            'reason' => $this->reason,
            'rejected_at' => now()->toDateTimeString(),
        ];
    }
}

