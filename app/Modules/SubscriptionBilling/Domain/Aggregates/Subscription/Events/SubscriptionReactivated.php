<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events;

use DateTimeImmutable;

final readonly class SubscriptionReactivated
{
    public function __construct(public string $subscriptionId, public string $tenantId, public DateTimeImmutable $occurredAt) {}
}
