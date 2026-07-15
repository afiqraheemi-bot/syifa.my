<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class PlanOfferingStorageRecord
{
    public function __construct(
        public string $id,
        public string $planId,
        public string $billingOptionId,
        public int $amountMinor,
        public string $currencyCode,
        public string $status,
        public DateTimeImmutable $effectiveStart,
        public ?DateTimeImmutable $effectiveEnd,
        public string $configurationVersion,
        public string $capabilityConfigurationReference,
        public int $displayOrder,
        public int $version,
    ) {}
}
