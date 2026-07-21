<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

use DateTimeImmutable;

interface SubscriptionIntegrationOutboxRepositoryInterface
{
    public function add(SubscriptionActivatedIntegrationEvent $event): void;

    /** @return list<SubscriptionActivatedIntegrationEvent> */
    public function pending(DateTimeImmutable $availableAt, int $limit = 100): array;

    public function claimNext(DateTimeImmutable $now, int $leaseSeconds = 120): ?SubscriptionIntegrationOutboxClaim;

    public function completeDispatch(string $eventId, string $leaseToken, DateTimeImmutable $dispatchedAt): bool;

    public function releaseForRetry(string $eventId, string $leaseToken, DateTimeImmutable $nextRetryAt, string $safeFailureLabel, DateTimeImmutable $now): bool;
}
