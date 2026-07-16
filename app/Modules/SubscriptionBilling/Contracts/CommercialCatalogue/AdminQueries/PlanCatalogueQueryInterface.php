<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanData;

/**
 * Ordering is deterministic and mandatory: displayOrder ascending, then code
 * ascending, then planId ascending. Sorting is the responsibility of the
 * implementing query adapter, never the Application layer.
 */
interface PlanCatalogueQueryInterface
{
    public function listPlans(OffsetPaginationInput $pagination): PaginatedPlanData;
}
