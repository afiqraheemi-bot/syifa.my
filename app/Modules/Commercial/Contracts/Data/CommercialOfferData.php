<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Data;

final readonly class CommercialOfferData
{
    /**
     * @param  list<CommercialOfferLineItemData>  $lineItems
     */
    public function __construct(
        public string $id,
        public string $platformIdentityId,
        public string $clinicRegistrationId,
        public ?string $tenantId,
        public string $status,
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
        public string $expiresAt,
        public ?string $claimedPaymentId,
        public ?string $claimedAt,
        public ?string $cancelledAt,
        public ?string $expiredAt,
        public int $version,
        public array $lineItems,
    ) {}
}
