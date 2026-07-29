<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notification\Application;

use App\Modules\Notification\Application\DeliverNotificationService;
use App\Modules\Notification\Contracts\NotificationRepositoryInterface;
use App\Modules\Notification\Domain\DeliveryAttempt;
use App\Modules\Notification\Domain\Notification;
use App\Modules\Notification\Domain\NotificationStatus;
use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeliverNotificationServiceTest extends TestCase
{
    public function test_third_transport_failure_exhausts_delivery_retries(): void
    {
        $at = new DateTimeImmutable('2026-07-29T04:00:00+08:00');
        $notification = new Notification(
            '00000000-0000-4000-8000-000000000001',
            null,
            '00000000-0000-4000-8000-000000000002',
            'booking_received',
            'booking',
            'booking-1',
            'booking-1:received',
            'clinic-owner',
            'owner@example.test',
            'Booking received',
            'A booking was received.',
            NotificationStatus::Delayed,
            $at,
            $at->modify('+2 minutes'),
            [
                new DeliveryAttempt(1, $at->modify('+1 minute'), 'temporary_failure', true, 'transport_failure'),
                new DeliveryAttempt(2, $at->modify('+2 minutes'), 'temporary_failure', true, 'transport_failure'),
            ],
            3,
        );
        $repository = new DeliveryNotificationRepository($notification);
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('raw')->willThrowException(new RuntimeException('SMTP unavailable.'));

        $this->expectException(RuntimeException::class);

        try {
            (new DeliverNotificationService($repository, $mailer))
                ->execute($notification->id, $at->modify('+3 minutes'));
        } finally {
            self::assertSame(NotificationStatus::Failed, $notification->status);
            self::assertCount(3, $notification->attempts);
            self::assertSame('permanent_failure', $notification->attempts[2]->outcome);
            self::assertFalse($notification->attempts[2]->retryEligible);
        }
    }
}

final class DeliveryNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(private Notification $notification) {}

    public function find(string $notificationId): ?Notification
    {
        return $notificationId === $this->notification->id ? $this->notification : null;
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Notification
    {
        return null;
    }

    public function save(Notification $notification): void
    {
        $this->notification = $notification;
    }
}
