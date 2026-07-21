<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Jobs\VerifyProviderWebhookReceiptJob;

final readonly class LaravelProviderVerificationJobDispatcher implements ProviderVerificationJobDispatcherInterface
{
    public function dispatch(string $receiptId, int $delaySeconds = 0): void
    {
        VerifyProviderWebhookReceiptJob::dispatch($receiptId)
            ->onConnection((string) config('payment_providers.verification.queue_connection', 'redis'))
            ->onQueue((string) config('payment_providers.verification.queue_name', 'payment-verification'))
            ->delay($delaySeconds)
            ->afterCommit();
    }
}
