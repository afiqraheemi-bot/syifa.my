<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class PaymentProviderConfiguration
{
    public function __construct(
        public string $providerKey,
        public bool $enabled,
        public bool $verificationPassed,
        public bool $webhookConfigured,
        public bool $providerReady,
        public bool $isDefault,
    ) {}

    public function canActivate(bool $credentialsConfigured): bool
    {
        return $credentialsConfigured
            && $this->verificationPassed
            && $this->webhookConfigured
            && $this->providerReady;
    }
}
