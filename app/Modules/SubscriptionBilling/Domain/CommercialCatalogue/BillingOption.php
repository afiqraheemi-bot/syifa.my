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

final readonly class BillingOption
{
    public function __construct(
        public BillingOptionId $id,
        public BillingOptionCode $code,
        public BillingOptionName $name,
        public CatalogueAvailability $availability,
        public RecurrenceClassification $recurrence,
        public ?BillingDuration $duration,
        public EffectivePeriod $effectivePeriod,
        public int $displayOrder,
    ) {
        if ($displayOrder < 0) {
            throw new InvalidCommercialCatalogueValueException('Billing Option display order cannot be negative.');
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
