<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

interface ProviderVerificationJobDispatcherInterface
{
    public function dispatch(string $receiptId, int $delaySeconds = 0): void;
}
