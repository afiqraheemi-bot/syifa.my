<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

interface SubscriptionSummaryReadInterface
{
    public function summary(string $trustedTenantId): ?SubscriptionSummaryData;
}
