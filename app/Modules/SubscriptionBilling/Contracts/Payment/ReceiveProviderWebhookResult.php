<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class ReceiveProviderWebhookResult
{
    public function __construct(public bool $wasDuplicate) {}
}
