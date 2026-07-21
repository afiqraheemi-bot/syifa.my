<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderUnavailableException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;

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

    public function verify(ProviderPaymentVerificationRequest $request): ProviderPaymentVerification
    {
        throw new PaymentProviderUnavailableException('Payment provider is unavailable.');
    }

    /** @param array<string, string|list<string>> $headers */
    public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent
    {
        throw new PaymentProviderUnavailableException('Payment provider is unavailable.');
    }

    public function credentialsConfigured(): bool
    {
        return false;
    }

    public function verifyConfiguration(): ProviderConfigurationVerification
    {
        return new ProviderConfigurationVerification(false, 'unavailable');
    }
}
