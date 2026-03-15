<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeletionRequestApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Account deletion completed'))
            ->line(__('Your account deletion request has been approved. Your account and associated data have been permanently removed.'))
            ->line(__('If you did not request this, please contact support.'));
    }
}
