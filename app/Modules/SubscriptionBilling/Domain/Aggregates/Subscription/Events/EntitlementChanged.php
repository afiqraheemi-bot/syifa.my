<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events;

use DateTimeImmutable;

final readonly class EntitlementChanged
{
    public function __construct(
        public string $subscriptionId,
        public string $tenantId,
        public string $previousPlanId,
        public string $previousBillingCycleId,
        public int $previousAmountMinor,
        public string $previousCurrencyCode,
        public string $previousBillingPeriodStartsOn,
        public string $previousBillingPeriodEndsOn,
        public string $previousConfigurationVersion,
        public string $previousEntitlementStatus,
        public string $newPlanId,
        public string $newBillingCycleId,
        public int $newAmountMinor,
        public string $newCurrencyCode,
        public string $newBillingPeriodStartsOn,
        public string $newBillingPeriodEndsOn,
        public string $newConfigurationVersion,
        public string $newEntitlementStatus,
        public DateTimeImmutable $occurredAt,
    ) {}
}
