<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionValueException;

final readonly class Entitlement
{
    /** @var list<CapabilityKey> */
    public array $capabilities;

    /** @param list<CapabilityKey> $capabilities */
    public function __construct(
        public PlanId $planId,
        public BillingCycleId $billingCycleId,
        public string $configurationVersion,
        public EntitlementStatus $status,
        array $capabilities,
    ) {
        if ($configurationVersion === '' || mb_strlen($configurationVersion) > 100) {
            throw new InvalidSubscriptionValueException('Entitlement configuration version is required.');
        }

        $values = array_map(static fn (CapabilityKey $capability): string => $capability->value, $capabilities);
        if (count($values) !== count(array_unique($values))) {
            throw new InvalidSubscriptionValueException('Entitlement capabilities must be unique.');
        }

        $this->capabilities = $capabilities;
    }

    public function has(CapabilityKey $capability): bool
    {
        foreach ($this->capabilities as $included) {
            if ($included->value === $capability->value) {
                return true;
            }
        }

        return false;
    }

    public function withStatus(EntitlementStatus $status): self
    {
        return new self($this->planId, $this->billingCycleId, $this->configurationVersion, $status, $this->capabilities);
    }

    public function equals(self $other): bool
    {
        $capabilities = array_map(static fn (CapabilityKey $capability): string => $capability->value, $this->capabilities);
        $otherCapabilities = array_map(
            static fn (CapabilityKey $capability): string => $capability->value,
            $other->capabilities,
        );
        sort($capabilities);
        sort($otherCapabilities);

        return $this->planId->value === $other->planId->value
            && $this->billingCycleId->value === $other->billingCycleId->value
            && $this->configurationVersion === $other->configurationVersion
            && $this->status === $other->status
            && $capabilities === $otherCapabilities;
    }
}
