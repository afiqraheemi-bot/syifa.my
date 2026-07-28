<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\BillingOverview;

final readonly class BillingOverviewData
{
    /** @param list<RecentPaymentData> $recentPayments */
    public function __construct(
        public int $activeSubscriptions,
        public int $expiringSubscriptions,
        public int $expiredSubscriptions,
        public int $annualRevenueMinor,
        public string $currency,
        public array $recentPayments,
        public int $pendingPayments,
        public int $succeededPayments,
        public int $failedPayments,
        public int $openReconciliationCases,
    ) {}
}
