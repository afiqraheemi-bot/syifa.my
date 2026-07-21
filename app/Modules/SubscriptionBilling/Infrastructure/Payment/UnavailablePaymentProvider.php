<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderUnavailableException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;

final class UnavailablePaymentProvider implements PaymentProviderInterface
{
    public function providerKey(): string
    {
        return 'provider-neutral';
    }

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult
    {
        throw new PaymentProviderUnavailableException('No selected Payment provider is configured for Payment Core.');
    }
}
