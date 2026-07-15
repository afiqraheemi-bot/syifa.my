<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingDuration;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionName;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CatalogueAvailability;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\EffectivePeriod;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\RecurrenceClassification;

final class BillingOption
{
    public function __construct(
        public readonly BillingOptionId $id,
        public readonly BillingOptionCode $code,
        public readonly BillingOptionName $name,
        public readonly CatalogueAvailability $availability,
        public readonly RecurrenceClassification $recurrence,
        public readonly ?BillingDuration $duration,
        public readonly EffectivePeriod $effectivePeriod,
        public readonly int $displayOrder,
        private int $version = 0,
    ) {
        if ($displayOrder < 0) {
            throw new InvalidCommercialCatalogueValueException('Billing Option display order cannot be negative.');
        }

        if ($version < 0) {
            throw new InvalidCommercialCatalogueValueException('Billing Option version cannot be negative.');
        }

        $this->assertClassificationAndDuration();
    }

    public function isNonRecurring(): bool
    {
        return $this->recurrence === RecurrenceClassification::NonRecurring;
    }

    public function isAvailableOn(string $calendarDate): bool
    {
        return ! $this->isNonRecurring()
            && $this->availability === CatalogueAvailability::Available
            && $this->effectivePeriod->includes($calendarDate);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version !== $this->version + 1) {
            throw new InvalidCommercialCatalogueValueException(
                'Billing Option version must advance exactly one step.',
            );
        }

        $this->version = $version;
    }

    private function assertClassificationAndDuration(): void
    {
        if ($this->recurrence === RecurrenceClassification::NonRecurring) {
            if ($this->duration !== null) {
                throw new InvalidCommercialCatalogueValueException('Non-recurring Billing Option cannot have a duration.');
            }

            if ($this->availability !== CatalogueAvailability::Unavailable) {
                throw new InvalidCommercialCatalogueValueException(
                    'Non-recurring Billing Option must remain unavailable in Phase 1.',
                );
            }

            return;
        }

        if ($this->duration === null) {
            throw new InvalidCommercialCatalogueValueException(
                'Recurring Billing Option must carry a duration.',
            );
        }
    }
}
