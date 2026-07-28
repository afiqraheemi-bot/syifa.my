<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application;

use App\Modules\Notification\Contracts\NotificationRepositoryInterface;
use App\Modules\Notification\Domain\NotificationStatus;
use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use RuntimeException;
use Throwable;

final readonly class DeliverNotificationService
{
    public function __construct(
        private NotificationRepositoryInterface $notifications,
        private Mailer $mailer,
    ) {}

    public function execute(string $notificationId, DateTimeImmutable $now): void
    {
        $notification = $this->notifications->find($notificationId);
        if ($notification === null) {
            throw new RuntimeException('Notification was not found for delivery.');
        }
        if (in_array($notification->status, [NotificationStatus::Sent, NotificationStatus::Delivered], true)) {
            return;
        }
        if (! in_array($notification->status, [NotificationStatus::Queued, NotificationStatus::Delayed], true)) {
            throw new RuntimeException('Notification is not eligible for delivery.');
        }

        try {
            $this->mailer->raw(
                $notification->body,
                static function ($message) use ($notification): void {
                    $message->to($notification->recipientEmail);
                    $message->subject($notification->subject);
                },
            );
            $notification->recordAccepted($now);
            $this->notifications->save($notification);
        } catch (Throwable $exception) {
            $notification->recordFailure($now, count($notification->attempts) < 3, 'transport_failure');
            $this->notifications->save($notification);

            throw new RuntimeException('Notification delivery failed safely.', 0, $exception);
        }
    }
}
