<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Entitlements;

/**
 * @param  list<string>  $capabilityKeys
 */
final readonly class ComputedSubscriptionEntitlementData
{
    /**
     * @param  list<string>  $capabilityKeys
     */
    public function __construct(
        public string $planId,
        public string $billingOptionId,
        public string $configurationVersion,
        public array $capabilityKeys,
    ) {}
}
