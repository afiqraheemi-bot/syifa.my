<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions\InvalidPaymentValueException;

final readonly class PaymentId
{
    public function __construct(public string $value)
    {
        if (trim($value) === '' || mb_strlen($value) > 120) {
            throw new InvalidPaymentValueException('Payment ID must be a non-empty opaque identifier.');
        }
    }
}
