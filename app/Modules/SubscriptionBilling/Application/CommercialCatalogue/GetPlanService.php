<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;

final readonly class GetPlanService
{
    public function __construct(private CommercialCatalogueQueryInterface $plans) {}

    public function execute(string $planId): ?PlanData
    {
        return $this->plans->findPlan($planId);
    }
}
