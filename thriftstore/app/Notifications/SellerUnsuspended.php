<?php

namespace App\Notifications;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SellerUnsuspended extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Seller $seller)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seller_unsuspended',
            'seller_id' => $this->seller->id,
            'store_name' => $this->seller->store_name,
            'status' => $this->seller->status,
            'at' => now()->toDateTimeString(),
        ];
    }
}

