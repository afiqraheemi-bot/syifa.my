<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions\InvalidPaymentValueException;

final readonly class PaymentAmount
{
    public function __construct(public int $minorUnits)
    {
        if ($minorUnits < 0) {
            throw new InvalidPaymentValueException('Payment amount cannot be negative.');
        }
    }
}
