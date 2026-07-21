<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

interface PaymentProviderConfigurationRepositoryInterface
{
    /** @return list<PaymentProviderConfiguration> */
    public function all(): array;

    public function find(string $providerKey): ?PaymentProviderConfiguration;

    public function default(): ?PaymentProviderConfiguration;

    public function save(PaymentProviderConfiguration $configuration): void;

    public function disable(string $providerKey): void;

    public function makeDefault(string $providerKey): void;

    /**
     * Reads a single configuration row with a row lock held for the
     * remainder of the current transaction. Must only be called from inside
     * an active transaction — callers are responsible for atomicity.
     */
    public function findForUpdate(string $providerKey): ?PaymentProviderConfiguration;

    /**
     * Reads every configuration row, ordered deterministically, with a row
     * lock held on each for the remainder of the current transaction. Used
     * where a mutation (default selection) must be serialized against every
     * other row, not just the one being changed. Must only be called from
     * inside an active transaction.
     *
     * @return list<PaymentProviderConfiguration>
     */
    public function allForUpdate(): array;
}
