<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\ReferenceData;

interface PlanOfferingQueryInterface
{
    /**
     * @return list<PlanOfferingReferenceData>
     */
    public function listAvailable(string $effectiveDate): array;

    public function resolveForCheckout(string $planOfferingId, string $effectiveDate): ?PlanOfferingReferenceData;
}
