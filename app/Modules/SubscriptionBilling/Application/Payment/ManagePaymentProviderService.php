<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfiguration;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfigurationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use DateTimeImmutable;

final readonly class ManagePaymentProviderService
{
    public function __construct(
        private PaymentProviderConfigurationRepositoryInterface $configurations,
        private PaymentProviderRegistryInterface $providers,
        private PaymentTransactionInterface $transactions,
        private PaymentProviderAuditTrail $audit,
    ) {}

    /** @return list<PaymentProviderConfiguration> */
    public function all(): array
    {
        return $this->configurations->all();
    }

    public function assess(
        string $providerKey,
        bool $webhookConfigured,
        bool $providerReady,
        ?string $platformIdentityId,
        DateTimeImmutable $occurredAt,
        string $correlationId,
    ): PaymentProviderConfiguration {
        // The live provider check is a network call and must never happen
        // inside a database transaction or while holding a row lock — the
        // same rule 31_PAYMENT_ARCHITECTURE_DESIGN.md already states for
        // Payment itself ("Call Payment Provider through provider
        // abstraction outside the database transaction").
        $provider = $this->providers->forExistingAttempt($providerKey);
        $verification = $provider->verifyConfiguration();

        return $this->transactions->run(function () use (
            $providerKey,
            $webhookConfigured,
            $providerReady,
            $provider,
            $verification,
            $platformIdentityId,
            $occurredAt,
            $correlationId,
        ): PaymentProviderConfiguration {
            $current = $this->configurations->findForUpdate($providerKey)
                ?? new PaymentProviderConfiguration($providerKey, false, false, false, false, false);
            $ready = $provider->credentialsConfigured() && $verification->passed && $webhookConfigured && $providerReady;
            $updated = new PaymentProviderConfiguration(
                $providerKey,
                $current->enabled && $ready,
                $verification->passed,
                $webhookConfigured,
                $providerReady,
                $current->isDefault && $ready,
            );
            $this->configurations->save($updated);
            $this->audit->record(
                'payment_provider.assess',
                $providerKey,
                $this->state($current),
                $this->state($updated),
                $platformIdentityId,
                AuditOutcomeType::Succeeded,
                null,
                $occurredAt,
                $correlationId,
            );

            return $updated;
        });
    }

    public function enable(
        string $providerKey,
        ?string $platformIdentityId,
        DateTimeImmutable $occurredAt,
        string $correlationId,
    ): PaymentProviderConfiguration {
        return $this->transactions->run(function () use ($providerKey, $platformIdentityId, $occurredAt, $correlationId): PaymentProviderConfiguration {
            $provider = $this->providers->forExistingAttempt($providerKey);
            $current = $this->requiredForUpdate($providerKey);

            if (! $current->canActivate($provider->credentialsConfigured())) {
                $this->audit->record(
                    'payment_provider.enable',
                    $providerKey,
                    $this->state($current),
                    $this->state($current),
                    $platformIdentityId,
                    AuditOutcomeType::Denied,
                    'activation_gates_not_satisfied',
                    $occurredAt,
                    $correlationId,
                );

                throw new PaymentProviderConfigurationException(
                    'Credentials, verification, webhook configuration, and provider readiness are required before activation.',
                );
            }

            $updated = new PaymentProviderConfiguration($providerKey, true, true, true, true, $current->isDefault);
            $this->configurations->save($updated);
            $this->audit->record(
                'payment_provider.enable',
                $providerKey,
                $this->state($current),
                $this->state($updated),
                $platformIdentityId,
                AuditOutcomeType::Succeeded,
                null,
                $occurredAt,
                $correlationId,
            );

            return $updated;
        });
    }

    public function disable(
        string $providerKey,
        ?string $platformIdentityId,
        DateTimeImmutable $occurredAt,
        string $correlationId,
    ): void {
        $this->transactions->run(function () use ($providerKey, $platformIdentityId, $occurredAt, $correlationId): void {
            $this->providers->forExistingAttempt($providerKey);
            $current = $this->requiredForUpdate($providerKey);

            if ($current->isDefault) {
                $this->audit->record(
                    'payment_provider.disable',
                    $providerKey,
                    $this->state($current),
                    $this->state($current),
                    $platformIdentityId,
                    AuditOutcomeType::Denied,
                    'provider_is_current_default',
                    $occurredAt,
                    $correlationId,
                );

                throw new PaymentProviderConfigurationException(
                    "Payment provider {$providerKey} is the current default and cannot be disabled until another provider is explicitly made default.",
                );
            }

            $this->configurations->disable($providerKey);
            $updated = $this->required($providerKey);
            $this->audit->record(
                'payment_provider.disable',
                $providerKey,
                $this->state($current),
                $this->state($updated),
                $platformIdentityId,
                AuditOutcomeType::Succeeded,
                null,
                $occurredAt,
                $correlationId,
            );
        });
    }

    public function makeDefault(
        string $providerKey,
        ?string $platformIdentityId,
        DateTimeImmutable $occurredAt,
        string $correlationId,
    ): PaymentProviderConfiguration {
        return $this->transactions->run(function () use ($providerKey, $platformIdentityId, $occurredAt, $correlationId): PaymentProviderConfiguration {
            // Locks every row (in a stable order) so this default-selection
            // cannot interleave with a concurrent disable/enable/assess on
            // any provider, including whichever one is currently default.
            $current = null;
            foreach ($this->configurations->allForUpdate() as $configuration) {
                if ($configuration->providerKey === $providerKey) {
                    $current = $configuration;
                    break;
                }
            }

            if ($current === null) {
                throw new PaymentProviderConfigurationException("Payment provider {$providerKey} has no configuration.");
            }

            $provider = $this->providers->forExistingAttempt($providerKey);

            if (! $current->enabled || ! $current->canActivate($provider->credentialsConfigured())) {
                $this->audit->record(
                    'payment_provider.make_default',
                    $providerKey,
                    $this->state($current),
                    $this->state($current),
                    $platformIdentityId,
                    AuditOutcomeType::Denied,
                    'activation_gates_not_satisfied',
                    $occurredAt,
                    $correlationId,
                );

                throw new PaymentProviderConfigurationException("Payment provider {$providerKey} is not active for new attempts.");
            }

            $this->configurations->makeDefault($providerKey);
            $updated = $this->required($providerKey);
            $this->audit->record(
                'payment_provider.make_default',
                $providerKey,
                $this->state($current),
                $this->state($updated),
                $platformIdentityId,
                AuditOutcomeType::Succeeded,
                null,
                $occurredAt,
                $correlationId,
            );

            return $updated;
        });
    }

    private function requiredForUpdate(string $providerKey): PaymentProviderConfiguration
    {
        return $this->configurations->findForUpdate($providerKey)
            ?? throw new PaymentProviderConfigurationException("Payment provider {$providerKey} has no configuration.");
    }

    private function required(string $providerKey): PaymentProviderConfiguration
    {
        return $this->configurations->find($providerKey)
            ?? throw new PaymentProviderConfigurationException("Payment provider {$providerKey} has no configuration.");
    }

    /** @return array<string, bool> */
    private function state(PaymentProviderConfiguration $configuration): array
    {
        return [
            'enabled' => $configuration->enabled,
            'verification_passed' => $configuration->verificationPassed,
            'webhook_configured' => $configuration->webhookConfigured,
            'provider_ready' => $configuration->providerReady,
            'is_default' => $configuration->isDefault,
        ];
    }
}
