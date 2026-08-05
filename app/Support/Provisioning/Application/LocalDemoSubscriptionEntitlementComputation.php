<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;

/**
 * Local/testing-only bridge for the demo acquisition journey.
 *
 * Production must use the governed entitlement computation boundary once its
 * catalogue packaging implementation is available.
 */
final readonly class LocalDemoSubscriptionEntitlementComputation implements SubscriptionEntitlementComputationInterface
{
    public function __construct(private CapabilityDefinitionCatalogueQueryInterface $capabilities) {}

    public function compute(ResolvedSubscriptionOfferingData $resolvedOffering): ComputedSubscriptionEntitlementData
    {
        $page = $this->capabilities->listCapabilityDefinitions(new OffsetPaginationInput(1, 100));
        $keys = [];

        foreach ($page->items as $capability) {
            if ($capability->status === 'active') {
                $keys[] = $capability->capabilityKey;
            }
        }

        sort($keys, SORT_STRING);

        return new ComputedSubscriptionEntitlementData(
            $resolvedOffering->planId,
            $resolvedOffering->billingOptionId,
            $resolvedOffering->capabilityConfigurationReference,
            array_values(array_unique($keys)),
        );
    }
}
