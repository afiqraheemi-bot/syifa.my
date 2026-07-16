<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingDuration;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingInterval;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionName;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CatalogueAvailability;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\EffectivePeriod;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\RecurrenceClassification;

final readonly class CreateBillingOptionService
{
    public function __construct(
        private CommercialCatalogueIdentifierGeneratorInterface $identifiers,
        private BillingOptionRepositoryInterface $billingOptions,
    ) {}

    public function execute(CreateBillingOptionCommand $command): BillingOption
    {
        try {
            $recurrence = RecurrenceClassification::from($command->recurrenceClassification);
            $duration = $this->duration($recurrence, $command->intervalUnit, $command->intervalCount);
        } catch (\ValueError $exception) {
            throw new InvalidCommercialCatalogueValueException(
                'The submitted Billing Option recurrence data cannot be translated into the approved domain model.',
                previous: $exception,
            );
        }
        $availability = $recurrence === RecurrenceClassification::NonRecurring
            ? CatalogueAvailability::Unavailable
            : CatalogueAvailability::Available;

        $billingOption = new BillingOption(
            new BillingOptionId($this->identifiers->generate()),
            new BillingOptionCode($command->code),
            new BillingOptionName($command->name),
            $availability,
            $recurrence,
            $duration,
            new EffectivePeriod($command->effectiveStart, $command->effectiveEnd),
            $command->displayOrder,
        );

        $this->billingOptions->save($billingOption);

        return $billingOption;
    }

    private function duration(
        RecurrenceClassification $recurrence,
        ?string $intervalUnit,
        ?int $intervalCount,
    ): ?BillingDuration {
        if ($recurrence === RecurrenceClassification::NonRecurring) {
            return null;
        }

        if ($intervalUnit === null || $intervalCount === null) {
            throw new InvalidCommercialCatalogueValueException(
                'Recurring billing options require an interval and a duration.',
            );
        }

        return new BillingDuration(BillingInterval::from($intervalUnit), $intervalCount);
    }
}
