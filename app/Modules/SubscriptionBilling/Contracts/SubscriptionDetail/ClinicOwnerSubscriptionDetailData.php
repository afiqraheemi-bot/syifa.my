<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail;

final readonly class ClinicOwnerSubscriptionDetailData
{
    public function __construct(
        public string $planName,
        public string $billingCycleName,
        public string $startsOn,
        public string $endsOn,
        public string $status,
        public string $renewalStatus,
        public ?string $latestPaymentStatus,
        public bool $renewalEligible,
    ) {}
}
