<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use DateTimeImmutable;

final readonly class ManualRenewSubscriptionCommand
{
    public function __construct(
        public string $subscriptionId,
        public string $actorId,
        public string $idempotencyKey,
        public int $expectedVersion,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
