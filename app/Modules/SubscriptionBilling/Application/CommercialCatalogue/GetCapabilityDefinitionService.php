<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;

final readonly class GetCapabilityDefinitionService
{
    public function __construct(private CommercialCatalogueQueryInterface $capabilities) {}

    public function execute(string $capabilityId): ?CapabilityDefinitionData
    {
        return $this->capabilities->findCapability($capabilityId);
    }
}
