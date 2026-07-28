<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use DateTimeImmutable;

final readonly class AutoRenewCommand
{
    public function __construct(
        public string $subscriptionId,
        public string $actorId,
        public int $expectedVersion,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
