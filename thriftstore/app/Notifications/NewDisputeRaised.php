<?php

namespace App\Notifications;

use App\Models\OrderDispute;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewDisputeRaised extends Notification
{
    use Queueable;

    public function __construct(public OrderDispute $dispute)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_dispute',
            'title' => 'New Dispute Raised',
            'message' => "Order #{$this->dispute->order_id} has a new dispute: {$this->dispute->reason_code}.",
            'dispute_id' => $this->dispute->id,
            'order_id' => $this->dispute->order_id,
            'action_url' => route('admin.orders'),
            'icon' => 'exclamation-triangle',
            'color' => 'red',
        ];
    }
}
