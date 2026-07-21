<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ProviderPaymentVerificationRequest
{
    public function __construct(
        public string $providerKey,
        public string $providerPaymentReference,
        public string $paymentId,
        public string $paymentAttemptReference,
        public int $expectedAmountMinor,
        public string $expectedCurrency,
    ) {}
}
