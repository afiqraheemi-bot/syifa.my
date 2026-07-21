<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ResolvedPaymentAttempt
{
    public function __construct(
        public string $paymentId,
        public string $attemptReference,
        public string $providerKey,
        public string $providerPaymentReference,
        public int $expectedAmountMinor,
        public string $expectedCurrency,
        public int $position,
        public bool $isCurrent,
    ) {}
}
