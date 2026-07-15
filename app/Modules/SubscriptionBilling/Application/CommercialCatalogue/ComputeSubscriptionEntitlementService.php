<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;

final readonly class ComputeSubscriptionEntitlementService
{
    public function __construct(private SubscriptionEntitlementComputationInterface $computation) {}

    public function execute(ResolvedSubscriptionOfferingData $resolvedOffering): ComputedSubscriptionEntitlementData
    {
        return $this->computation->compute($resolvedOffering);
    }
}
