<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

/**
 * Plan-offering read-model data.
 *
 * `effectiveStart` and `effectiveEnd` use the canonical `YYYY-MM-DD` format.
 */
final readonly class PlanOfferingData
{
    public function __construct(
        public string $planOfferingId,
        public string $planId,
        public string $billingOptionId,
        public int $amountMinor,
        public string $currencyCode,
        public string $status,
        public string $effectiveStart,
        public ?string $effectiveEnd,
        public string $configurationVersion,
        public string $capabilityConfigurationReference,
        public int $displayOrder,
    ) {}
}
