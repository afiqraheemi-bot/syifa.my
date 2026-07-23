<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

final readonly class SubscriptionSummaryData
{
    public function __construct(
        public string $status,
        public string $endsOn,
    ) {}
}
