<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ClinicOwnerSetupNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Set up your SYIFA.my Clinic Owner access')
            ->greeting('Welcome to SYIFA.my')
            ->line('Your clinic workspace is ready. Verify your email and set a secure password to continue.')
            ->action('Set up Clinic Owner access', $this->url)
            ->line('This secure link expires in 60 minutes.');
    }
}
