<?php

namespace App\Notifications;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public Seller $seller, public string $status)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $data = match($this->status) {
            'grace_period' => [
                'title' => 'Subscription Grace Period',
                'message' => 'Your subscription has expired. You have 7 days to renew before your shop is automatically closed.',
                'color' => 'orange',
                'icon' => 'clock',
            ],
            'lapsed' => [
                'title' => 'Subscription Lapsed',
                'message' => 'Your subscription has fully lapsed. Your shop has been closed to new orders.',
                'color' => 'red',
                'icon' => 'exclamation-circle',
            ],
            default => [
                'title' => 'Subscription Updated',
                'message' => "Your subscription status is now '{$this->status}'.",
                'color' => 'blue',
                'icon' => 'info-circle',
            ],
        };

        return array_merge([
            'type' => 'subscription_status',
            'action_url' => route('seller.payments'),
            'seller_id' => $this->seller->id,
        ], $data);
    }
}
