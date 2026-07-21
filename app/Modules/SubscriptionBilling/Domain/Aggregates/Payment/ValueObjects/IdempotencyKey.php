<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions\InvalidPaymentValueException;

final readonly class IdempotencyKey
{
    public function __construct(public string $value)
    {
        if (trim($value) === '' || mb_strlen($value) > 160) {
            throw new InvalidPaymentValueException('Idempotency key must be a non-empty opaque identifier.');
        }
    }
}
