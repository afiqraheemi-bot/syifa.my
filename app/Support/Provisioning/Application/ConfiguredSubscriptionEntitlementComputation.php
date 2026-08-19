<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use RuntimeException;

/**
 * Computes subscription capabilities from the governed package profile and
 * active catalogue definitions. Unknown profiles fail closed so production
 * can never accidentally grant every available capability.
 */
final readonly class ConfiguredSubscriptionEntitlementComputation implements SubscriptionEntitlementComputationInterface
{
    public function __construct(private CapabilityDefinitionCatalogueQueryInterface $capabilities) {}

    public function compute(ResolvedSubscriptionOfferingData $resolvedOffering): ComputedSubscriptionEntitlementData
    {
        $configuredKeys = config(sprintf(
            'subscription_packages.capability_profiles.%s',
            $resolvedOffering->capabilityConfigurationReference,
        ));
        if (! is_array($configuredKeys)) {
            throw new RuntimeException('Subscription capability profile is not configured.');
        }

        $activeKeys = [];
        foreach ($this->capabilities->listCapabilityDefinitions(new OffsetPaginationInput(1, 100))->items as $capability) {
            if ($capability->status === 'active') {
                $activeKeys[] = $capability->capabilityKey;
            }
        }

        $keys = array_values(array_intersect($configuredKeys, $activeKeys));
        sort($keys, SORT_STRING);

        if (count($keys) !== count(array_unique($configuredKeys))) {
            throw new RuntimeException('Subscription capability profile references an unavailable capability.');
        }

        return new ComputedSubscriptionEntitlementData(
            $resolvedOffering->planId,
            $resolvedOffering->billingOptionId,
            $resolvedOffering->capabilityConfigurationReference,
            $keys,
        );
    }
}
