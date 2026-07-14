<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

interface AvailablePlanOfferingQueryInterface
{
    /**
     * Canonical format: `YYYY-MM-DD`.
     * The returned list is intentionally unpaginated in Phase 1.
     * Future implementations must exclude draft, unavailable, grandfathered, retired, expired, non-recurring, inactive, and invalid capability configuration records.
     *
     * @param  string  $effectiveDate  Canonical calendar date in `YYYY-MM-DD` format.
     * @return list<PlanOfferingData>
     */
    public function listAvailableOfferings(string $effectiveDate): array;
}
