<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notification\Application;

use App\Modules\Notification\Application\Commands\PrepareNotificationCommand;
use App\Modules\Notification\Application\PrepareNotificationService;
use App\Modules\Notification\Contracts\NotificationDeliveryDispatcherInterface;
use App\Modules\Notification\Contracts\NotificationIdentifierGeneratorInterface;
use App\Modules\Notification\Contracts\NotificationRepositoryInterface;
use App\Modules\Notification\Contracts\NotificationTemplateReadInterface;
use App\Modules\Notification\Domain\Notification;
use App\Modules\Notification\Domain\NotificationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PrepareNotificationServiceTest extends TestCase
{
    public function test_it_prepares_and_queues_one_governed_transactional_notification_idempotently(): void
    {
        $repository = new InMemoryNotificationRepository;
        $delivery = new RecordedNotificationDelivery;
        $service = new PrepareNotificationService(
            $repository,
            new FixedNotificationTemplate,
            new FixedNotificationIdentifier,
            $delivery,
        );
        $command = new PrepareNotificationCommand(
            '00000000-0000-4000-8000-000000000001',
            'booking_received',
            'booking',
            'booking-1',
            'booking-1:received:clinic-owner',
            'clinic-owner',
            'owner@example.test',
            ['booking_reference' => 'SYIFA-001'],
        );

        $first = $service->execute($command, new DateTimeImmutable('2026-07-28T10:00:00Z'));
        $second = $service->execute($command, new DateTimeImmutable('2026-07-28T10:01:00Z'));

        self::assertSame($first, $second);
        self::assertSame(NotificationStatus::Queued, $first->status);
        self::assertSame('Booking SYIFA-001 received', $first->subject);
        self::assertSame(['00000000-0000-4000-8000-000000000003'], $delivery->notificationIds);
        self::assertCount(1, $repository->notifications);
    }
}

final class InMemoryNotificationRepository implements NotificationRepositoryInterface
{
    /** @var array<string, Notification> */
    public array $notifications = [];

    public function find(string $notificationId): ?Notification
    {
        return $this->notifications[$notificationId] ?? null;
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Notification
    {
        foreach ($this->notifications as $notification) {
            if ($notification->idempotencyKey === $idempotencyKey) {
                return $notification;
            }
        }

        return null;
    }

    public function save(Notification $notification): void
    {
        $this->notifications[$notification->id] = $notification;
    }
}

final readonly class FixedNotificationTemplate implements NotificationTemplateReadInterface
{
    public function activeFor(string $category): ?array
    {
        return [
            'id' => '00000000-0000-4000-8000-000000000002',
            'category' => $category,
            'subject' => 'Booking {{booking_reference}} received',
            'body' => 'A booking has been received safely.',
            'version' => 1,
        ];
    }
}

final readonly class FixedNotificationIdentifier implements NotificationIdentifierGeneratorInterface
{
    public function generate(): string
    {
        return '00000000-0000-4000-8000-000000000003';
    }
}

final class RecordedNotificationDelivery implements NotificationDeliveryDispatcherInterface
{
    /** @var list<string> */
    public array $notificationIds = [];

    public function dispatch(string $notificationId): void
    {
        $this->notificationIds[] = $notificationId;
    }
}
