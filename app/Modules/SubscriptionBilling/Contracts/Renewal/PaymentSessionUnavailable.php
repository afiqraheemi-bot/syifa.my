<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

final readonly class PaymentSessionUnavailable
{
    public function __construct(public string $reasonCode) {}
}
