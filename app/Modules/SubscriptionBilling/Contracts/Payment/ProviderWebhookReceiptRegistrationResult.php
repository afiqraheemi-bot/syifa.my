<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ProviderWebhookReceiptRegistrationResult
{
    public function __construct(
        public ProviderWebhookReceipt $receipt,
        public bool $wasDuplicate,
    ) {}
}
