<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

interface PaymentAttemptResolverInterface
{
    public function resolve(string $providerKey, string $providerPaymentReference): ?ResolvedPaymentAttempt;
}
