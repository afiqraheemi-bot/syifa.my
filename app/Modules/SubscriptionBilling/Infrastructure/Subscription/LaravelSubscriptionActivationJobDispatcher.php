<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\Jobs\ActivateSubscriptionJob;

final readonly class LaravelSubscriptionActivationJobDispatcher implements SubscriptionActivationJobDispatcherInterface
{
    public function dispatch(string $applicationId, int $delaySeconds = 0): void
    {
        ActivateSubscriptionJob::dispatch($applicationId)->onConnection('redis')->onQueue('subscription-activation')->delay($delaySeconds)->afterCommit();
    }
}
