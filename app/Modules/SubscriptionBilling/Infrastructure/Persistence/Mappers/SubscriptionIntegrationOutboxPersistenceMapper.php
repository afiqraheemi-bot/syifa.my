<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxClaim;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\SubscriptionIntegrationOutboxStorageRecord;

final class SubscriptionIntegrationOutboxPersistenceMapper
{
    public function toRecord(SubscriptionActivatedIntegrationEvent $event): SubscriptionIntegrationOutboxStorageRecord
    {
        return new SubscriptionIntegrationOutboxStorageRecord(
            $event->eventId, SubscriptionActivatedIntegrationEvent::TYPE, $event->eventVersion, $event->subscriptionId,
            $event->payload(), $event->occurredAt, null, null, null, 0, null, null,
        );
    }

    public function toEvent(SubscriptionIntegrationOutboxStorageRecord $record): SubscriptionActivatedIntegrationEvent
    {
        $payload = $record->payload;

        return new SubscriptionActivatedIntegrationEvent(
            (string) $payload['event_id'], (string) $payload['subscription_id'], (string) $payload['tenant_id'],
            (string) $payload['clinic_registration_id'], (string) $payload['payment_id'], (string) $payload['commercial_offer_id'],
            (string) $payload['plan_id'], (string) $payload['billing_cycle_id'], (string) $payload['starts_on'],
            (string) $payload['ends_on'], new \DateTimeImmutable((string) $payload['occurred_at']), (int) $payload['event_version'],
        );
    }

    public function toClaim(SubscriptionIntegrationOutboxStorageRecord $record): SubscriptionIntegrationOutboxClaim
    {
        if ($record->claimToken === null || $record->leaseExpiresAt === null) {
            throw new \RuntimeException('A claimed Subscription outbox record requires a lease token and expiry.');
        }

        return new SubscriptionIntegrationOutboxClaim($this->toEvent($record), $record->claimToken, $record->leaseExpiresAt, $record->attemptCount);
    }
}
