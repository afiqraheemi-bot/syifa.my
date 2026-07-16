<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanOfferingData;

/**
 * Ordering is deterministic and mandatory: effectiveStart ascending, then
 * displayOrder ascending, then planOfferingId ascending. Sorting is the
 * responsibility of the implementing query adapter, never the Application layer.
 */
interface PlanOfferingCatalogueQueryInterface
{
    public function listPlanOfferings(OffsetPaginationInput $pagination): PaginatedPlanOfferingData;
}
