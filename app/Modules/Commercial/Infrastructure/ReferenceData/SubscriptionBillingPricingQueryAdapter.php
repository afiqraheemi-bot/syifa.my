<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\ReferenceData;

use App\Modules\Commercial\Contracts\ReferenceData\PricingQueryInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PricingReferenceData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;

final readonly class SubscriptionBillingPricingQueryAdapter implements PricingQueryInterface
{
    public function __construct(private CommercialCatalogueQueryInterface $catalogue) {}

    public function findCurrentPrice(string $planOfferingId): ?PricingReferenceData
    {
        $offering = $this->catalogue->findPlanOffering($planOfferingId);

        if ($offering === null || $offering->currencyCode !== 'MYR') {
            return null;
        }

        return new PricingReferenceData(
            $offering->planOfferingId,
            $offering->amountMinor,
            $offering->currencyCode,
            $offering->configurationVersion,
        );
    }
}
