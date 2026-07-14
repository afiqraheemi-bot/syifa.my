<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionValueException;

final readonly class CapabilityKey
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z][a-z0-9._-]{0,99}$/', $value) !== 1) {
            throw new InvalidSubscriptionValueException('Capability key has an invalid configuration identifier.');
        }
    }
}
