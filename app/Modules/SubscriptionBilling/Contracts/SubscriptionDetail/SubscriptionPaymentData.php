<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail;

final readonly class SubscriptionPaymentData
{
    public function __construct(
        public string $paymentId,
        public string $purpose,
        public int $amountMinor,
        public string $currency,
        public string $status,
        public string $occurredAt,
    ) {}
}
