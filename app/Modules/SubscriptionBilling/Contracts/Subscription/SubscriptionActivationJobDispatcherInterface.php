<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

interface SubscriptionActivationJobDispatcherInterface
{
    public function dispatch(string $applicationId, int $delaySeconds = 0): void;
}
