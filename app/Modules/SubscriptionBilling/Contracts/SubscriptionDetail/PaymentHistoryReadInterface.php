<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail;

interface PaymentHistoryReadInterface
{
    /** @return list<SubscriptionPaymentData> */
    public function listForSubscription(string $subscriptionId, ?string $cursor, int $limit): array;
}
