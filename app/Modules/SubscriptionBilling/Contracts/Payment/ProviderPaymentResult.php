<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ProviderPaymentResult
{
    public function __construct(
        public string $providerKey,
        public string $providerPaymentReference,
    ) {}
}
