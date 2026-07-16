<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedCapabilityDefinitionData;

/**
 * Ordering is deterministic and mandatory: capabilityKey ascending, then
 * capabilityId ascending. Sorting is the responsibility of the implementing
 * query adapter, never the Application layer.
 */
interface CapabilityDefinitionCatalogueQueryInterface
{
    public function listCapabilityDefinitions(OffsetPaginationInput $pagination): PaginatedCapabilityDefinitionData;
}
