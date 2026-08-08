<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\ReferenceData;

interface PlanQueryInterface
{
    public function findActivePlan(string $planId): ?PlanReferenceData;
}
