<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ProviderPaymentVerification
{
    public function __construct(
        public string $providerKey,
        public string $providerPaymentReference,
        public string $status,
        public int $amountMinor,
        public string $currency,
    ) {}
}
