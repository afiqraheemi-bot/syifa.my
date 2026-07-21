<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions\InvalidPaymentValueException;

final readonly class PaymentCurrency
{
    public function __construct(public string $value)
    {
        if ($value !== 'MYR') {
            throw new InvalidPaymentValueException('Payment Core supports MYR only.');
        }
    }
}
