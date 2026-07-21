<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Data;

final readonly class CheckoutSnapshotData
{
    /**
     * @param  list<CommercialOfferLineItemData>  $lineItems
     */
    public function __construct(
        public string $planOfferingId,
        public string $planId,
        public string $billingCycleId,
        public string $billingPeriodStart,
        public string $billingPeriodEnd,
        public string $offeringConfigurationVersion,
        public string $capabilityConfigurationReference,
        public int $subtotalAmountMinor,
        public int $totalAmountMinor,
        public string $currency,
        public array $lineItems,
    ) {}
}
