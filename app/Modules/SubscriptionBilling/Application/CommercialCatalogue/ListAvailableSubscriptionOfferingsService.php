<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AvailablePlanOfferingQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;

final readonly class ListAvailableSubscriptionOfferingsService
{
    public function __construct(private AvailablePlanOfferingQueryInterface $offerings) {}

    /** @return list<PlanOfferingData> */
    public function execute(string $effectiveDate): array
    {
        return $this->offerings->listAvailableOfferings($effectiveDate);
    }
}
