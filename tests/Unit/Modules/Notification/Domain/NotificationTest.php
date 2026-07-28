<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notification\Domain;

use App\Modules\Notification\Domain\Notification;
use App\Modules\Notification\Domain\NotificationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NotificationTest extends TestCase
{
    public function test_delivery_outcomes_are_append_only_and_do_not_change_originating_truth(): void
    {
        $at = new DateTimeImmutable('2026-07-28T10:00:00Z');
        $notification = new Notification(
            '00000000-0000-4000-8000-000000000001',
            null,
            '00000000-0000-4000-8000-000000000002',
            'registration_decided',
            'clinic_registration',
            'registration-1',
            'registration-1:decision',
            'prospect',
            'prospect@example.test',
            'Registration update',
            'Your registration has been reviewed.',
            NotificationStatus::Prepared,
            $at,
            $at,
        );

        $notification->queue($at);
        $notification->recordFailure($at->modify('+1 minute'), true, 'transport_failure');

        self::assertSame(NotificationStatus::Delayed, $notification->status);
        self::assertCount(1, $notification->attempts);
        self::assertSame('temporary_failure', $notification->attempts[0]->outcome);
        self::assertSame('clinic_registration', $notification->triggerType);
        self::assertSame('registration-1', $notification->triggerId);
    }
}
