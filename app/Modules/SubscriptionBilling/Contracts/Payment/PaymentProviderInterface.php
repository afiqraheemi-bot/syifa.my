<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

interface PaymentProviderInterface
{
    public function providerKey(): string;

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult;

    public function verify(ProviderPaymentVerificationRequest $request): ProviderPaymentVerification;

    /** @param array<string, string|list<string>> $headers */
    public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent;

    public function credentialsConfigured(): bool;

    public function verifyConfiguration(): ProviderConfigurationVerification;
}
