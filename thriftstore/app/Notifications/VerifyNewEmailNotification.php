<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * B1 - v1.3: Sent to seller's new email; when they click the link, email is updated.
 */
class VerifyNewEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public string $newEmail
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->verificationUrl();

        return (new MailMessage)
            ->subject(__('Verify Your New Email Address'))
            ->line(__('You requested to change your email to :email.', ['email' => $this->newEmail]))
            ->line(__('Click the button below to verify this address. Your login email will then be updated.'))
            ->action(__('Verify New Email'), $url)
            ->line(__('This link will expire in 60 minutes. If you did not request this change, ignore this email.'));
    }

    protected function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'seller.email.verify-new',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $this->userId,
                'hash' => sha1($this->newEmail),
            ]
        );
    }
}
