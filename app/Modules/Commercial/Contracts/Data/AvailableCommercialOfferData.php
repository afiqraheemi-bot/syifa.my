<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Data;

final readonly class AvailableCommercialOfferData
{
    public function __construct(
        public string $planOfferingId,
        public string $planId,
        public string $billingCycleId,
        public string $planName,
        public string $billingCycleName,
        public int $amountMinor,
        public string $currency,
        public string $effectiveStart,
        public ?string $effectiveEnd,
        public string $configurationVersion,
        public int $displayOrder,
    ) {}
}
