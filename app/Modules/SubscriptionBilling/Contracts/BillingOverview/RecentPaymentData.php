<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\BillingOverview;

final readonly class RecentPaymentData
{
    public function __construct(
        public string $paymentId,
        public ?string $tenantId,
        public int $amountMinor,
        public string $currency,
        public string $status,
        public string $lastChangedAt,
    ) {}
}
