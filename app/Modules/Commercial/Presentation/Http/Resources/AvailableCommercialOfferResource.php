<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Presentation\Http\Resources;

use App\Modules\Commercial\Contracts\Data\AvailableCommercialOfferData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AvailableCommercialOfferData */
final class AvailableCommercialOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AvailableCommercialOfferData $offer */
        $offer = $this->resource;

        return [
            'plan_offering_id' => $offer->planOfferingId,
            'plan_id' => $offer->planId,
            'billing_cycle_id' => $offer->billingCycleId,
            'plan_name' => $offer->planName,
            'billing_cycle_name' => $offer->billingCycleName,
            'amount_minor' => $offer->amountMinor,
            'currency' => $offer->currency,
            'effective_start' => $offer->effectiveStart,
            'effective_end' => $offer->effectiveEnd,
            'configuration_version' => $offer->configurationVersion,
            'display_order' => $offer->displayOrder,
        ];
    }
}
