<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Renewal;

final readonly class PreparedRenewalOffer
{
    public function __construct(
        public string $commercialOfferId,
        public string $subscriptionId,
        public string $planId,
        public string $billingCycleId,
        public int $amountMinor,
        public string $currency,
        public string $expiresAt,
        public string $startsOn,
        public string $endsOn,
        public string $offeringConfigurationReference,
    ) {}
}
