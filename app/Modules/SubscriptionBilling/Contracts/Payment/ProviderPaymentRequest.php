<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ProviderPaymentRequest
{
    public function __construct(
        public string $paymentId,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public string $correlationId,
        public ?string $paymentAttemptReference = null,
    ) {}
}
