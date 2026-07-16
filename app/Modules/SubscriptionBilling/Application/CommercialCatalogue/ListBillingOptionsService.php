<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\BillingOptionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedBillingOptionData;

final readonly class ListBillingOptionsService
{
    public function __construct(private BillingOptionCatalogueQueryInterface $billingOptions) {}

    public function execute(OffsetPaginationInput $pagination): PaginatedBillingOptionData
    {
        return $this->billingOptions->listBillingOptions($pagination);
    }
}
