<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Application;

use App\Modules\AcquisitionOffer\Application\Exceptions\CommercialSelectionUnavailableException;
use App\Modules\AcquisitionOffer\Contracts\Data\CheckoutSnapshotData;
use App\Modules\AcquisitionOffer\Contracts\Data\CommercialOfferLineItemData;
use App\Modules\AcquisitionOffer\Contracts\ReferenceData\PlanOfferingQueryInterface;
use DateTimeImmutable;

final readonly class ResolveCommercialSelectionService
{
    public function __construct(private PlanOfferingQueryInterface $planOfferings) {}

    public function execute(string $planOfferingId, DateTimeImmutable $occurredAt): CheckoutSnapshotData
    {
        $resolved = $this->planOfferings->resolveForCheckout($planOfferingId, $occurredAt->format('Y-m-d'));

        if ($resolved === null) {
            throw new CommercialSelectionUnavailableException('Commercial selection is unavailable.');
        }

        $lineItem = new CommercialOfferLineItemData(
            itemType: 'plan_offering',
            itemReference: $resolved->planOfferingId,
            description: sprintf('%s — %s', $resolved->planName, $resolved->billingCycleName),
            quantity: 1,
            unitAmountMinor: $resolved->amountMinor,
            totalAmountMinor: $resolved->amountMinor,
            currency: $resolved->currency,
            catalogueSnapshotReference: $resolved->configurationVersion,
        );

        return new CheckoutSnapshotData(
            planOfferingId: $resolved->planOfferingId,
            planId: $resolved->planId,
            billingCycleId: $resolved->billingCycleId,
            billingPeriodStart: $resolved->billingPeriodStart,
            billingPeriodEnd: $resolved->billingPeriodEnd,
            offeringConfigurationVersion: $resolved->configurationVersion,
            capabilityConfigurationReference: $resolved->capabilityConfigurationReference,
            subtotalAmountMinor: $resolved->amountMinor,
            totalAmountMinor: $resolved->amountMinor,
            currency: $resolved->currency,
            lineItems: [$lineItem],
        );
    }
}
