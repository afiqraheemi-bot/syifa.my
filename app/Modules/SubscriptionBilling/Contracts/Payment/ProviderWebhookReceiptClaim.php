<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ProviderWebhookReceiptClaim
{
    public function __construct(public ProviderWebhookReceipt $receipt, public string $claimToken) {}
}
