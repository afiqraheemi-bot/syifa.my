<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

/**
 * Trusted commercial resolution only.
 *
 * Calendar dates use the canonical `YYYY-MM-DD` format.
 * This data object must not expose authoritative capability keys.
 */
final readonly class ResolvedSubscriptionOfferingData
{
    public function __construct(
        public string $planOfferingId,
        public string $planId,
        public string $billingOptionId,
        public int $amountMinor,
        public string $currencyCode,
        public string $billingPeriodStart,
        public string $billingPeriodEnd,
        public string $offeringConfigurationVersion,
        public string $capabilityConfigurationReference,
    ) {}
}
