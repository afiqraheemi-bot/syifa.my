<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\BillingOverview;

final readonly class SubscriptionOverviewData
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
    ) {}
}
