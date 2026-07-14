<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events;

use DateTimeImmutable;

final readonly class SubscriptionCreated
{
    public function __construct(
        public string $subscriptionId,
        public string $tenantId,
        public string $planId,
        public string $billingCycleId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
