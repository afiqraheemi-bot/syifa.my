<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanLifecycle;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanName;
use DateTimeImmutable;

final class Plan
{
    public function __construct(
        public readonly PlanId $id,
        public readonly PlanName $name,
        public readonly PlanCode $code,
        public readonly string $description,
        public readonly PlanLifecycle $lifecycle,
        public readonly int $displayOrder,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $lastChangedAt,
        private int $version = 0,
    ) {
        if ($description === '' || trim($description) !== $description || mb_strlen($description) > 1000) {
            throw new InvalidCommercialCatalogueValueException(
                'Plan description must be a normalized value of at most 1000 characters.',
            );
        }

        if ($displayOrder < 0) {
            throw new InvalidCommercialCatalogueValueException('Plan display order cannot be negative.');
        }

        if ($lastChangedAt < $createdAt) {
            throw new InvalidCommercialCatalogueValueException(
                'Plan cannot have changed before it was created.',
            );
        }

        if ($version < 0) {
            throw new InvalidCommercialCatalogueValueException('Plan version cannot be negative.');
        }
    }

    public function isAvailableForNewPurchase(): bool
    {
        return $this->lifecycle->allowsNewPurchase();
    }

    public function isAvailableForRenewal(): bool
    {
        return $this->lifecycle->allowsRenewal();
    }

    public function activate(DateTimeImmutable $occurredAt): self
    {
        return $this->withLifecycle($this->lifecycle->activate(), $occurredAt);
    }

    public function makeUnavailable(DateTimeImmutable $occurredAt): self
    {
        return $this->withLifecycle($this->lifecycle->makeUnavailable(), $occurredAt);
    }

    public function grandfather(DateTimeImmutable $occurredAt): self
    {
        return $this->withLifecycle($this->lifecycle->grandfather(), $occurredAt);
    }

    public function retire(DateTimeImmutable $occurredAt): self
    {
        return $this->withLifecycle($this->lifecycle->retire(), $occurredAt);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version !== $this->version + 1) {
            throw new InvalidCommercialCatalogueValueException('Plan version must advance exactly one step.');
        }

        $this->version = $version;
    }

    private function withLifecycle(PlanLifecycle $lifecycle, DateTimeImmutable $occurredAt): self
    {
        if ($occurredAt < $this->lastChangedAt) {
            throw new InvalidCommercialCatalogueValueException('Plan lifecycle change cannot be backdated.');
        }

        return new self(
            $this->id,
            $this->name,
            $this->code,
            $this->description,
            $lifecycle,
            $this->displayOrder,
            $this->createdAt,
            $occurredAt,
            $this->version,
        );
    }
}
