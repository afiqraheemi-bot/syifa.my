<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

interface ProviderVerificationClockInterface
{
    public function now(): DateTimeImmutable;
}
