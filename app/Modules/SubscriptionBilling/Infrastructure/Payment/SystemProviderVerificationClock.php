<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationClockInterface;
use DateTimeImmutable;

final readonly class SystemProviderVerificationClock implements ProviderVerificationClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
