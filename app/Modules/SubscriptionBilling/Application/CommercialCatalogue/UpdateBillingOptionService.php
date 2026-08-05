<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingDuration;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingInterval;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionName;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CatalogueAvailability;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\EffectivePeriod;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\RecurrenceClassification;
use ValueError;

/**
 * Billing Option currently uses governed availability rather than a full lifecycle
 * state machine — `availability` is an editable governed field on this Update
 * command, not a named lifecycle transition. No Billing Option lifecycle endpoint
 * is approved; HTTP delivery must treat availability as an editable governed field
 * only, until a later Domain decision supersedes it.
 */
final readonly class UpdateBillingOptionService
{
    private const string AUDIT_ACTION = 'commercial_catalogue.billing_option.update';

    public function __construct(
        private BillingOptionRepositoryInterface $billingOptions,
        private BillingOptionAuditTrail $audit,
    ) {}

    public function execute(UpdateBillingOptionCommand $command): BillingOption
    {
        $billingOption = $this->requireBillingOption($command->billingOptionId);
        $this->assertExpectedVersion($billingOption, $command->expectedVersion);

        $recurrence = $this->recurrence($command->recurrenceClassification);
        $duration = $this->duration($recurrence, $command->intervalUnit, $command->intervalCount);

        $updated = new BillingOption(
            $billingOption->id,
            $billingOption->code,
            new BillingOptionName($command->name),
            $recurrence === RecurrenceClassification::NonRecurring
                ? CatalogueAvailability::Unavailable
                : CatalogueAvailability::from($command->availability),
            $recurrence,
            $duration,
            new EffectivePeriod($command->effectiveStart, $command->effectiveEnd),
            $command->displayOrder,
            $billingOption->version(),
        );

        $this->billingOptions->save($updated);
        $this->audit->record(
            self::AUDIT_ACTION,
            $updated,
            $billingOption->version(),
            $command->occurredAt,
            $command->actorPlatformIdentityId,
            $command->correlationId,
        );

        return $updated;
    }

    private function requireBillingOption(string $billingOptionId): BillingOption
    {
        $billingOption = $this->billingOptions->findById(new BillingOptionId($billingOptionId));

        if ($billingOption === null) {
            throw new CommercialCatalogueResourceNotFoundException('Billing Option', $billingOptionId);
        }

        return $billingOption;
    }

    private function assertExpectedVersion(BillingOption $billingOption, int $expectedVersion): void
    {
        if ($billingOption->version() !== $expectedVersion) {
            throw new CommercialCatalogueVersionMismatchException(
                'Billing Option',
                $billingOption->id->value,
                $expectedVersion,
                $billingOption->version(),
            );
        }
    }

    private function recurrence(string $value): RecurrenceClassification
    {
        try {
            return RecurrenceClassification::from($value);
        } catch (ValueError $exception) {
            throw new InvalidCommercialCatalogueValueException(
                'The submitted Billing Option recurrence data cannot be translated into the approved domain model.',
                previous: $exception,
            );
        }
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
