<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events;

use DateTimeImmutable;

final readonly class SubscriptionExpired
{
    public function __construct(public string $subscriptionId, public string $tenantId, public DateTimeImmutable $occurredAt) {}
}
