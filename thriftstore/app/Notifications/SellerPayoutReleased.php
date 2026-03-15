<?php

namespace App\Notifications;

use App\Models\SellerPayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SellerPayoutReleased extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SellerPayout $payout)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seller_payout_released',
            'payout_id' => $this->payout->id,
            'order_id' => $this->payout->order_id,
            'seller_id' => $this->payout->seller_id,
            'gross_amount' => (float) $this->payout->gross_amount,
            'platform_fee_amount' => (float) $this->payout->platform_fee_amount,
            'net_amount' => (float) $this->payout->net_amount,
            'status' => $this->payout->status,
            'released_at' => optional($this->payout->released_at)->toDateTimeString(),
        ];
    }
}
