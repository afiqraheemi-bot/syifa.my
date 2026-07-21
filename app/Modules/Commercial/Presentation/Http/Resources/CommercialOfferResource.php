<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Presentation\Http\Resources;

use App\Modules\Commercial\Contracts\Data\CommercialOfferData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommercialOfferData */
final class CommercialOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CommercialOfferData $offer */
        $offer = $this->resource;

        return [
            'id' => $offer->id,
            'status' => $offer->status,
            'clinic_registration_id' => $offer->clinicRegistrationId,
            'checkout_snapshot' => [
                'plan_offering_id' => $offer->planOfferingId,
                'plan_id' => $offer->planId,
                'billing_cycle_id' => $offer->billingCycleId,
                'billing_period_start' => $offer->billingPeriodStart,
                'billing_period_end' => $offer->billingPeriodEnd,
                'offering_configuration_version' => $offer->offeringConfigurationVersion,
                'capability_configuration_reference' => $offer->capabilityConfigurationReference,
            ],
            'totals' => [
                'subtotal_amount_minor' => $offer->subtotalAmountMinor,
                'total_amount_minor' => $offer->totalAmountMinor,
                'currency' => $offer->currency,
            ],
            'expires_at' => $offer->expiresAt,
            'claimed_payment_id' => $offer->claimedPaymentId,
            'claimed_at' => $offer->claimedAt,
            'cancelled_at' => $offer->cancelledAt,
            'expired_at' => $offer->expiredAt,
            'version' => $offer->version,
            'line_items' => array_map(
                static fn ($lineItem): array => [
                    'item_type' => $lineItem->itemType,
                    'item_reference' => $lineItem->itemReference,
                    'description' => $lineItem->description,
                    'quantity' => $lineItem->quantity,
                    'unit_amount_minor' => $lineItem->unitAmountMinor,
                    'total_amount_minor' => $lineItem->totalAmountMinor,
                    'currency' => $lineItem->currency,
                    'catalogue_snapshot_reference' => $lineItem->catalogueSnapshotReference,
                ],
                $offer->lineItems,
            ),
        ];
    }
}
