<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

final readonly class SubscriptionOfferingSelectionData
{
    /**
     * Canonical format: `YYYY-MM-DD`.
     * No capability, price, entitlement, or other commercial snapshot data may be supplied here.
     */
    public function __construct(
        public string $planOfferingId,
        public string $intendedEffectiveDate,
    ) {}
}
