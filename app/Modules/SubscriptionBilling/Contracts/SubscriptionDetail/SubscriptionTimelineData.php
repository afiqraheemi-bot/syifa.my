<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail;

final readonly class SubscriptionTimelineData
{
    public function __construct(
        public string $id,
        public string $eventType,
        public string $occurredAt,
    ) {}
}
