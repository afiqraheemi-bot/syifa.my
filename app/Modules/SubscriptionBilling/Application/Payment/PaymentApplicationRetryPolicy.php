<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

final readonly class PaymentApplicationRetryPolicy
{
    public function __construct(public int $leaseSeconds = 120, public int $maxAttempts = 5, public int $baseDelay = 5, public int $maxDelay = 120) {}

    public function delay(int $attempt): int
    {
        $base = min($this->maxDelay, $this->baseDelay * (2 ** max(0, $attempt - 1)));

        return min($this->maxDelay, $base + random_int(0, (int) floor($base * .2)));
    }
}
