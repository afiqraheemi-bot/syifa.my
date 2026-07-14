<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityStatus;

final readonly class CapabilityDefinition
{
    public function __construct(
        public CapabilityId $id,
        public CapabilityKey $key,
        public string $name,
        public string $description,
        public string $commercialMeaning,
        public CapabilityStatus $status,
    ) {
        if ($name === '' || trim($name) !== $name || mb_strlen($name) > 100) {
            throw new InvalidCommercialCatalogueValueException(
                'Capability name must be a normalized value of at most 100 characters.',
            );
        }

        if ($description === '' || trim($description) !== $description || mb_strlen($description) > 1000) {
            throw new InvalidCommercialCatalogueValueException(
                'Capability description must be a normalized value of at most 1000 characters.',
            );
        }

        if ($commercialMeaning === '' || trim($commercialMeaning) !== $commercialMeaning
            || mb_strlen($commercialMeaning) > 1000) {
            throw new InvalidCommercialCatalogueValueException(
                'Capability commercial meaning must be a normalized value of at most 1000 characters.',
            );
        }
    }

    public function isAvailableForNewPackaging(): bool
    {
        return $this->status === CapabilityStatus::Active;
    }

    public function isHistoricallyReferenceable(): bool
    {
        return $this->status !== CapabilityStatus::Draft;
    }

    public function activate(): self
    {
        return $this->transitionTo(CapabilityStatus::Active, [CapabilityStatus::Draft]);
    }

    public function deprecate(): self
    {
        return $this->transitionTo(CapabilityStatus::Deprecated, [CapabilityStatus::Active]);
    }

    public function retire(): self
    {
        return $this->transitionTo(CapabilityStatus::Retired, [
            CapabilityStatus::Draft,
            CapabilityStatus::Active,
            CapabilityStatus::Deprecated,
        ]);
    }

    /** @param list<CapabilityStatus> $allowedFrom */
    private function transitionTo(CapabilityStatus $status, array $allowedFrom): self
    {
        if (! in_array($this->status, $allowedFrom, true)) {
            throw new InvalidCommercialCatalogueValueException(sprintf(
                'Capability cannot transition from %s to %s.',
                $this->status->value,
                $status->value,
            ));
        }

        return new self(
            $this->id,
            $this->key,
            $this->name,
            $this->description,
            $this->commercialMeaning,
            $status,
        );
    }
}
