<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

interface PaymentProviderRegistryInterface
{
    public function defaultForNewAttempt(): PaymentProviderInterface;

    public function forNewAttempt(string $providerKey): PaymentProviderInterface;

    public function forExistingAttempt(string $providerKey): PaymentProviderInterface;
}
