<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Entitlements;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;

interface SubscriptionEntitlementComputationInterface
{
    /**
     * Produces the authoritative capability-key snapshot from a trusted resolved offering.
     */
    public function compute(
        ResolvedSubscriptionOfferingData $resolvedOffering,
    ): ComputedSubscriptionEntitlementData;
}
