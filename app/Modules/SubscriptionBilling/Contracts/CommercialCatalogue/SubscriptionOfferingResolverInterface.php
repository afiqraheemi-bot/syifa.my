<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

interface SubscriptionOfferingResolverInterface
{
    /**
     * Returns null when the selection is unavailable, invalid, ineligible, or unresolved.
     * The resolved offering is trusted commercial data only and never exposes authoritative capability keys.
     */
    public function resolve(SubscriptionOfferingSelectionData $selection): ?ResolvedSubscriptionOfferingData;
}
