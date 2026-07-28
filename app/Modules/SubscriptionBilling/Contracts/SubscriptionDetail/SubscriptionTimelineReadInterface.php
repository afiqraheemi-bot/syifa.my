<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail;

interface SubscriptionTimelineReadInterface
{
    /** @return list<SubscriptionTimelineData> */
    public function list(string $subscriptionId, ?string $cursor, int $limit): array;
}
