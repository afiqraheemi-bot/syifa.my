<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionValueException;

final readonly class BillingCycleId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new InvalidSubscriptionValueException('Billing Cycle ID must be a UUID.');
        }
    }
}
