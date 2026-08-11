<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

use DateTimeImmutable;

final readonly class ChangeSubscriptionPlanCommand
{
    public function __construct(
        public string $subscriptionId,
        public string $planOfferingId,
        public string $actorId,
        public int $expectedVersion,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
