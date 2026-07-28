<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

interface PricingHistoryReadInterface
{
    /**
     * @return list<PricingHistoryData>
     */
    public function forPlanOffering(string $planOfferingId): array;
}
