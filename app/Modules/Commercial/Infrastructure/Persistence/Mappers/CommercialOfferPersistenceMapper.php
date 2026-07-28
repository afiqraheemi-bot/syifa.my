<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\Persistence\Mappers;

use App\Modules\Commercial\Domain\CommercialOffer;
use App\Modules\Commercial\Domain\ValueObjects\CheckoutSnapshot;
use App\Modules\Commercial\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferLineItem;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferStatus;
use App\Modules\Commercial\Domain\ValueObjects\OfferExpiry;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\Commercial\Domain\ValueObjects\PriceSnapshot;
use App\Modules\Commercial\Domain\ValueObjects\TenantId;
use App\Modules\Commercial\Infrastructure\Persistence\Records\CommercialOfferLineItemStorageRecord;
use App\Modules\Commercial\Infrastructure\Persistence\Records\CommercialOfferStorageRecord;

final class CommercialOfferPersistenceMapper
{
    public function offerRecord(CommercialOffer $offer): CommercialOfferStorageRecord
    {
        $snapshot = $offer->checkoutSnapshot;

        return new CommercialOfferStorageRecord(
            $offer->id->value,
            $offer->platformIdentity?->value,
            $offer->clinicRegistration->value,
            $offer->tenantId?->value,
            $offer->status->value,
            $snapshot->planOfferingId,
            $snapshot->planId,
            $snapshot->billingCycleId,
            $snapshot->billingPeriodStart,
            $snapshot->billingPeriodEnd,
            $snapshot->offeringConfigurationVersion,
            $snapshot->capabilityConfigurationReference,
            $snapshot->subtotal->amountMinor,
            $snapshot->total->amountMinor,
            $snapshot->total->currency,
            $offer->expiry->expiresAt,
            $offer->claimedPaymentId,
            $offer->claimedAt,
            $offer->cancelledAt,
            $offer->expiredAt,
            $offer->correlationId,
            $offer->version(),
        );
    }

    /**
     * @return list<CommercialOfferLineItemStorageRecord>
     */
    public function lineItemRecords(CommercialOffer $offer): array
    {
        return array_map(
            static fn (CommercialOfferLineItem $lineItem, int $position): CommercialOfferLineItemStorageRecord => new CommercialOfferLineItemStorageRecord(
                $offer->id->value,
                $lineItem->itemType,
                $lineItem->itemReference,
                $lineItem->description,
                $lineItem->quantity,
                $lineItem->unitPrice->amountMinor,
                $lineItem->totalPrice->amountMinor,
                $lineItem->totalPrice->currency,
                $lineItem->catalogueSnapshotReference,
                $position,
            ),
            $offer->checkoutSnapshot->lineItems,
            array_keys($offer->checkoutSnapshot->lineItems),
        );
    }

    /**
     * @param  list<CommercialOfferLineItemStorageRecord>  $lineItems
     */
    public function toDomain(CommercialOfferStorageRecord $record, array $lineItems): CommercialOffer
    {
        return new CommercialOffer(
            id: new CommercialOfferId($record->id),
            platformIdentity: $record->platformIdentityId === null
                ? null
                : new PlatformIdentityReference($record->platformIdentityId),
            clinicRegistration: new ClinicRegistrationReference($record->clinicRegistrationId),
            tenantId: $record->tenantId === null ? null : new TenantId($record->tenantId),
            status: CommercialOfferStatus::from($record->status),
            checkoutSnapshot: new CheckoutSnapshot(
                $record->planOfferingId,
                $record->planId,
                $record->billingCycleId,
                $record->billingPeriodStart,
                $record->billingPeriodEnd,
                $record->offeringConfigurationVersion,
                $record->capabilityConfigurationReference,
                array_map(
                    static fn (CommercialOfferLineItemStorageRecord $lineItem): CommercialOfferLineItem => new CommercialOfferLineItem(
                        $lineItem->itemType,
                        $lineItem->itemReference,
                        $lineItem->description,
                        $lineItem->quantity,
                        new PriceSnapshot($lineItem->unitAmountMinor, $lineItem->currency),
                        new PriceSnapshot($lineItem->totalAmountMinor, $lineItem->currency),
                        $lineItem->catalogueSnapshotReference,
                    ),
                    $lineItems,
                ),
                new PriceSnapshot($record->subtotalAmountMinor, $record->currency),
                new PriceSnapshot($record->totalAmountMinor, $record->currency),
            ),
            expiry: new OfferExpiry($record->expiresAt),
            claimedPaymentId: $record->claimedPaymentId,
            claimedAt: $record->claimedAt,
            cancelledAt: $record->cancelledAt,
            expiredAt: $record->expiredAt,
            correlationId: $record->correlationId,
            version: $record->version,
        );
    }
}
