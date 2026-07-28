<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail;

interface SubscriptionDetailReadInterface
{
    public function detail(string $subscriptionId): ?SubscriptionDetailData;
}
