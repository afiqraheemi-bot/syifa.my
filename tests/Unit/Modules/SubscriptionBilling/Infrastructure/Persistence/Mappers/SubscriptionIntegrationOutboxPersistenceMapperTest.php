<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionIntegrationOutboxPersistenceMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SubscriptionIntegrationOutboxPersistenceMapperTest extends TestCase
{
    public function test_event_creation_and_payload_mapping_are_exact_and_provider_neutral(): void
    {
        $event = $this->event();
        $mapper = new SubscriptionIntegrationOutboxPersistenceMapper;
        $record = $mapper->toRecord($event);
        $restored = $mapper->toEvent($record);

        self::assertSame([
            'event_id', 'event_version', 'subscription_id', 'tenant_id', 'clinic_registration_id', 'payment_id',
            'commercial_offer_id', 'plan_id', 'billing_cycle_id', 'starts_on', 'ends_on', 'occurred_at',
        ], array_keys($record->payload));
        self::assertSame(SubscriptionActivatedIntegrationEvent::TYPE, $record->eventType);
        self::assertSame($event->payload(), $restored->payload());
        self::assertStringNotContainsString('provider', json_encode($record->payload, JSON_THROW_ON_ERROR));
    }

    private function event(): SubscriptionActivatedIntegrationEvent
    {
        return new SubscriptionActivatedIntegrationEvent(
            $this->uuid(1), $this->uuid(2), $this->uuid(3), $this->uuid(4), $this->uuid(5),
            $this->uuid(6), $this->uuid(7), $this->uuid(8), '2026-07-25', '2027-07-24', new DateTimeImmutable('2026-07-25T00:00:00Z'),
        );
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
