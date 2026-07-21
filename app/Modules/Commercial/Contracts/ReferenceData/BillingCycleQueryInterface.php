<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\ReferenceData;

interface BillingCycleQueryInterface
{
    public function findActiveBillingCycle(string $billingCycleId): ?BillingCycleReferenceData;
}
