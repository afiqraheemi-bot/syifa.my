<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\ReferenceData;

final readonly class PricingReferenceData
{
    public function __construct(
        public string $planOfferingId,
        public int $amountMinor,
        public string $currency,
        public string $configurationVersion,
    ) {}
}
