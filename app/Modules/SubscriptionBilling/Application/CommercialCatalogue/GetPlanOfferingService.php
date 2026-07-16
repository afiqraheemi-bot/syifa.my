<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;

final readonly class GetPlanOfferingService
{
    public function __construct(private CommercialCatalogueQueryInterface $planOfferings) {}

    public function execute(string $planOfferingId): ?PlanOfferingData
    {
        return $this->planOfferings->findPlanOffering($planOfferingId);
    }
}
