<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedCapabilityDefinitionData;

final readonly class ListCapabilityDefinitionsService
{
    public function __construct(private CapabilityDefinitionCatalogueQueryInterface $capabilities) {}

    public function execute(OffsetPaginationInput $pagination): PaginatedCapabilityDefinitionData
    {
        return $this->capabilities->listCapabilityDefinitions($pagination);
    }
}
