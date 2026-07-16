<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanData;

final readonly class ListPlansService
{
    public function __construct(private PlanCatalogueQueryInterface $plans) {}

    public function execute(OffsetPaginationInput $pagination): PaginatedPlanData
    {
        return $this->plans->listPlans($pagination);
    }
}
