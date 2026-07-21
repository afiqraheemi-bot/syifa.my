<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

interface PaymentProviderInterface
{
    public function providerKey(): string;

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult;
}
