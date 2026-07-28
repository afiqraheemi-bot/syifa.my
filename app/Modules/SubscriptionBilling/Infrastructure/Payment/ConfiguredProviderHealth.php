<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfigurationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ProviderHealth;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ProviderHealthInterface;
use DateTimeImmutable;

final readonly class ConfiguredProviderHealth implements ProviderHealthInterface
{
    /** @param iterable<PaymentProviderInterface> $providers */
    public function __construct(
        private iterable $providers,
        private PaymentProviderConfigurationRepositoryInterface $configurations,
    ) {}

    public function all(): array
    {
        $now = new DateTimeImmutable;
        $health = [];
        foreach ($this->providers as $provider) {
            $configuration = $this->configurations->find($provider->providerKey());
            $credentials = $provider->credentialsConfigured();
            $active = $configuration !== null
                && $configuration->enabled
                && $configuration->canActivate($credentials);
            $reason = match (true) {
                $configuration === null => 'configuration_missing',
                ! $credentials => 'credentials_missing',
                ! $configuration->enabled => 'disabled',
                ! $configuration->verificationPassed => 'verification_required',
                ! $configuration->webhookConfigured => 'webhook_required',
                ! $configuration->providerReady => 'provider_not_ready',
                default => 'ready',
            };
            $health[] = new ProviderHealth(
                $provider->providerKey(),
                $active ? 'operational' : 'unavailable',
                $active,
                $now,
                $reason,
            );
        }

        return $health;
    }
}
