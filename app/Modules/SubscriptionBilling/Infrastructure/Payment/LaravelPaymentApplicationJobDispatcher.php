<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentApplicationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Jobs\ApplyPaymentVerificationJob;

final readonly class LaravelPaymentApplicationJobDispatcher implements PaymentApplicationJobDispatcherInterface
{
    public function dispatch(string $applicationId, int $delaySeconds = 0): void
    {
        ApplyPaymentVerificationJob::dispatch($applicationId)->onConnection('redis')->onQueue('payment-application')->delay($delaySeconds)->afterCommit();
    }
}
