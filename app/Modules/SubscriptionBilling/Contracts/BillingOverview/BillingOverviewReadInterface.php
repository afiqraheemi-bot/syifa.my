<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\BillingOverview;

interface BillingOverviewReadInterface
{
    public function summary(string $asOfDate): BillingOverviewData;

    /** @return list<SubscriptionOverviewData> */
    public function subscriptions(
        ?string $status,
        ?string $cursor,
        int $limit,
        ?string $search,
    ): array;
}
