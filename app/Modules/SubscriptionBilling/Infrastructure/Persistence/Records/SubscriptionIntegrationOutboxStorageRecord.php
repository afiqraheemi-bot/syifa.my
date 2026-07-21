<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class SubscriptionIntegrationOutboxStorageRecord
{
    /** @param array<string, int|string> $payload */
    public function __construct(
        public string $id, public string $eventType, public int $eventVersion, public string $subscriptionId,
        public array $payload, public DateTimeImmutable $occurredAt, public ?DateTimeImmutable $publishedAt,
        public ?string $claimToken, public ?DateTimeImmutable $leaseExpiresAt, public int $attemptCount,
        public ?DateTimeImmutable $nextAttemptAt, public ?string $safeFailureLabel,
    ) {}
}
