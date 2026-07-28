<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail;

final readonly class SubscriptionDetailData
{
    public function __construct(
        public string $subscriptionId,
        public string $tenantId,
        public string $planId,
        public string $billingCycleId,
        public int $amountMinor,
        public string $currency,
        public string $startsOn,
        public string $endsOn,
        public string $status,
        public string $renewalStatus,
        public string $autoRenewStatus,
        public int $version,
        public ?string $renewalCheckoutId = null,
    ) {}
}
