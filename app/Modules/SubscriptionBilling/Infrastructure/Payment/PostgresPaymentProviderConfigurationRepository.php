<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfiguration;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfigurationRepositoryInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresPaymentProviderConfigurationRepository implements PaymentProviderConfigurationRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function all(): array
    {
        $configurations = [];
        foreach ($this->connection->table('payment_provider_configurations')->orderBy('provider_key')->get() as $row) {
            $configurations[] = $this->map($row);
        }

        return $configurations;
    }

    public function find(string $providerKey): ?PaymentProviderConfiguration
    {
        $row = $this->connection->table('payment_provider_configurations')->where('provider_key', $providerKey)->first();

        return $row instanceof stdClass ? $this->map($row) : null;
    }

    public function default(): ?PaymentProviderConfiguration
    {
        $row = $this->connection->table('payment_provider_configurations')->where('is_default', true)->first();

        return $row instanceof stdClass ? $this->map($row) : null;
    }

    public function findForUpdate(string $providerKey): ?PaymentProviderConfiguration
    {
        $row = $this->connection->table('payment_provider_configurations')
            ->where('provider_key', $providerKey)
            ->lockForUpdate()
            ->first();

        return $row instanceof stdClass ? $this->map($row) : null;
    }

    public function allForUpdate(): array
    {
        $configurations = [];
        foreach (
            $this->connection->table('payment_provider_configurations')
                ->orderBy('provider_key')
                ->lockForUpdate()
                ->get() as $row
        ) {
            $configurations[] = $this->map($row);
        }

        return $configurations;
    }

    public function save(PaymentProviderConfiguration $configuration): void
    {
        $now = now();
        $values = [
            'enabled' => $configuration->enabled,
            'verification_passed' => $configuration->verificationPassed,
            'webhook_configured' => $configuration->webhookConfigured,
            'provider_ready' => $configuration->providerReady,
            'is_default' => $configuration->isDefault,
            'updated_at' => $now,
        ];
        $query = $this->connection->table('payment_provider_configurations')->where('provider_key', $configuration->providerKey);
        if ($query->exists()) {
            $query->update($values);
        } else {
            $this->connection->table('payment_provider_configurations')->insert([
                'provider_key' => $configuration->providerKey,
                ...$values,
                'created_at' => $now,
            ]);
        }
    }

    public function disable(string $providerKey): void
    {
        $this->connection->table('payment_provider_configurations')->where('provider_key', $providerKey)
            ->update(['enabled' => false, 'is_default' => false, 'updated_at' => now()]);
    }

    public function makeDefault(string $providerKey): void
    {
        $this->connection->transaction(function () use ($providerKey): void {
            $this->connection->table('payment_provider_configurations')->update(['is_default' => false, 'updated_at' => now()]);
            $this->connection->table('payment_provider_configurations')->where('provider_key', $providerKey)
                ->update(['is_default' => true, 'updated_at' => now()]);
        });
    }

    private function map(stdClass $row): PaymentProviderConfiguration
    {
        return new PaymentProviderConfiguration(
            providerKey: (string) $row->provider_key,
            enabled: (bool) $row->enabled,
            verificationPassed: (bool) $row->verification_passed,
            webhookConfigured: (bool) $row->webhook_configured,
            providerReady: (bool) $row->provider_ready,
            isDefault: (bool) $row->is_default,
        );
    }
}
