<?php

namespace App\Notifications;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SellerAccountApprovedNotification extends Notification
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
            'type' => 'seller_approved',
            'title' => 'Seller Account Approved',
            'message' => "Congratulations! Your seller account '{$this->seller->store_name}' has been approved. You can now start listing products and selling on the platform.",
            'seller_id' => $this->seller->id,
            'action_url' => route('seller.dashboard'),
            'icon' => 'check-circle',
            'color' => 'green',
        ];
    }
}
