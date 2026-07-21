<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfigurationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;

final class PaymentProviderRegistry implements PaymentProviderRegistryInterface
{
    /** @var array<string, PaymentProviderInterface> */
    private array $providers = [];

    /** @param iterable<PaymentProviderInterface> $providers */
    public function __construct(
        iterable $providers,
        private readonly PaymentProviderConfigurationRepositoryInterface $configurations,
    ) {
        foreach ($providers as $provider) {
            $key = $provider->providerKey();
            if (isset($this->providers[$key])) {
                throw new PaymentProviderConfigurationException("Duplicate payment provider: {$key}.");
            }
            $this->providers[$key] = $provider;
        }
    }

    public function defaultForNewAttempt(): PaymentProviderInterface
    {
        $configuration = $this->configurations->default();
        if ($configuration === null) {
            throw new PaymentProviderConfigurationException('No default payment provider is configured.');
        }

        return $this->forNewAttempt($configuration->providerKey);
    }

    public function forNewAttempt(string $providerKey): PaymentProviderInterface
    {
        $provider = $this->provider($providerKey);
        $configuration = $this->configurations->find($providerKey);

        if ($configuration === null || ! $configuration->enabled || ! $configuration->canActivate($provider->credentialsConfigured())) {
            throw new PaymentProviderConfigurationException("Payment provider {$providerKey} is not active for new attempts.");
        }

        return $provider;
    }

    public function forExistingAttempt(string $providerKey): PaymentProviderInterface
    {
        return $this->provider($providerKey);
    }

    private function provider(string $providerKey): PaymentProviderInterface
    {
        return $this->providers[$providerKey]
            ?? throw new PaymentProviderConfigurationException("Payment provider {$providerKey} is not registered.");
    }
}
