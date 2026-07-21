<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Subscription;

final readonly class SubscriptionActivationRetryPolicy
{
    public function __construct(public int $leaseSeconds = 120, public int $maxAttempts = 5) {}
}
