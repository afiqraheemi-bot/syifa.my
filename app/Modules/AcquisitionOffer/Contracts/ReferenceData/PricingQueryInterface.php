<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\ReferenceData;

interface PricingQueryInterface
{
    public function findCurrentPrice(string $planOfferingId): ?PricingReferenceData;
}
