<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanOfferingData;

final readonly class ListPlanOfferingsService
{
    public function __construct(private PlanOfferingCatalogueQueryInterface $planOfferings) {}

    public function execute(OffsetPaginationInput $pagination): PaginatedPlanOfferingData
    {
        return $this->planOfferings->listPlanOfferings($pagination);
    }
}
