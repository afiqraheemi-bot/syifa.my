<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Application;

use App\Modules\Commercial\Contracts\Data\CommercialOfferData;
use App\Modules\Commercial\Contracts\Data\CommercialOfferLineItemData;
use App\Modules\Commercial\Domain\CommercialOffer;
use DateTimeInterface;

final readonly class CommercialOfferDataAssembler
{
    public function fromDomain(CommercialOffer $offer): CommercialOfferData
    {
        $snapshot = $offer->checkoutSnapshot;

        return new CommercialOfferData(
            id: $offer->id->value,
            platformIdentityId: $offer->platformIdentity?->value,
            clinicRegistrationId: $offer->clinicRegistration->value,
            tenantId: $offer->tenantId?->value,
            status: $offer->status->value,
            planOfferingId: $snapshot->planOfferingId,
            planId: $snapshot->planId,
            billingCycleId: $snapshot->billingCycleId,
            billingPeriodStart: $snapshot->billingPeriodStart,
            billingPeriodEnd: $snapshot->billingPeriodEnd,
            offeringConfigurationVersion: $snapshot->offeringConfigurationVersion,
            capabilityConfigurationReference: $snapshot->capabilityConfigurationReference,
            subtotalAmountMinor: $snapshot->subtotal->amountMinor,
            totalAmountMinor: $snapshot->total->amountMinor,
            currency: $snapshot->total->currency,
            expiresAt: $offer->expiry->expiresAt->format(DateTimeInterface::ATOM),
            claimedPaymentId: $offer->claimedPaymentId,
            claimedAt: $offer->claimedAt?->format(DateTimeInterface::ATOM),
            cancelledAt: $offer->cancelledAt?->format(DateTimeInterface::ATOM),
            expiredAt: $offer->expiredAt?->format(DateTimeInterface::ATOM),
            version: $offer->version(),
            lineItems: array_map(
                static fn ($lineItem): CommercialOfferLineItemData => new CommercialOfferLineItemData(
                    $lineItem->itemType,
                    $lineItem->itemReference,
                    $lineItem->description,
                    $lineItem->quantity,
                    $lineItem->unitPrice->amountMinor,
                    $lineItem->totalPrice->amountMinor,
                    $lineItem->totalPrice->currency,
                    $lineItem->catalogueSnapshotReference,
                ),
                $snapshot->lineItems,
            ),
        );
    }
}
