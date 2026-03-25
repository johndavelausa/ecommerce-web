<?php

namespace App\Notifications;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSellerRegistered extends Notification
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
            'type' => 'new_seller_registration',
            'title' => 'New Seller Registration',
            'message' => "A new seller '{$this->seller->store_name}' has registered and is pending approval.",
            'seller_id' => $this->seller->id,
            'action_url' => route('admin.sellers'),
            'icon' => 'user-plus',
            'color' => 'blue',
        ];
    }
}
