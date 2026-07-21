<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\ReferenceData;

final readonly class PlanOfferingReferenceData
{
    public function __construct(
        public string $planOfferingId,
        public string $planId,
        public string $billingCycleId,
        public string $planName,
        public string $billingCycleName,
        public int $amountMinor,
        public string $currency,
        public string $billingPeriodStart,
        public string $billingPeriodEnd,
        public string $configurationVersion,
        public string $capabilityConfigurationReference,
        public int $displayOrder,
    ) {}
}
