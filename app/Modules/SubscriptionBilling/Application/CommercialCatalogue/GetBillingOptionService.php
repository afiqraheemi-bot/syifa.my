<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;

final readonly class GetBillingOptionService
{
    public function __construct(private CommercialCatalogueQueryInterface $billingOptions) {}

    public function execute(string $billingOptionId): ?BillingOptionData
    {
        return $this->billingOptions->findBillingOption($billingOptionId);
    }
}
