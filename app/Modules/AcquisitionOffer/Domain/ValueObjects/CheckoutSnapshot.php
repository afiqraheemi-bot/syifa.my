<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Domain\ValueObjects;

use App\Modules\AcquisitionOffer\Domain\Exceptions\InvalidCommercialOfferValueException;

final readonly class CheckoutSnapshot
{
    /**
     * @param  list<CommercialOfferLineItem>  $lineItems
     */
    public function __construct(
        public string $planOfferingId,
        public string $planId,
        public string $billingCycleId,
        public string $billingPeriodStart,
        public string $billingPeriodEnd,
        public string $offeringConfigurationVersion,
        public string $capabilityConfigurationReference,
        public array $lineItems,
        public PriceSnapshot $subtotal,
        public PriceSnapshot $total,
    ) {
        foreach ([
            'plan offering id' => $planOfferingId,
            'plan id' => $planId,
            'billing cycle id' => $billingCycleId,
            'billing period start' => $billingPeriodStart,
            'billing period end' => $billingPeriodEnd,
            'offering configuration version' => $offeringConfigurationVersion,
            'capability configuration reference' => $capabilityConfigurationReference,
        ] as $label => $value) {
            if (trim($value) === '' || mb_strlen($value) > 255) {
                throw new InvalidCommercialOfferValueException(sprintf('%s is invalid.', ucfirst($label)));
            }
        }

        if ($lineItems === []) {
            throw new InvalidCommercialOfferValueException('Checkout snapshot must contain at least one line item.');
        }

        $computedSubtotal = 0;
        foreach ($lineItems as $lineItem) {
            if ($lineItem->totalPrice->currency !== $subtotal->currency) {
                throw new InvalidCommercialOfferValueException('Checkout snapshot currencies must be consistent.');
            }

            $computedSubtotal += $lineItem->totalPrice->amountMinor;
        }

        if ($computedSubtotal !== $subtotal->amountMinor || $subtotal->amountMinor !== $total->amountMinor) {
            throw new InvalidCommercialOfferValueException('Checkout snapshot totals are inconsistent.');
        }
    }
}
