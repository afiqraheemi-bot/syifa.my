<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanOfferingException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\EffectivePeriod;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingStatus;

final class PlanOffering
{
    public function __construct(
        public readonly PlanOfferingId $id,
        public readonly PlanId $planId,
        public readonly BillingOptionId $billingOptionId,
        public readonly Money $currentPrice,
        public readonly EffectivePeriod $effectivePeriod,
        public readonly PlanOfferingStatus $status,
        public readonly string $configurationVersion,
        public readonly string $capabilityConfigurationReference,
        public readonly int $displayOrder,
        private int $version = 0,
    ) {
        if ($currentPrice->currencyCode !== 'MYR') {
            throw new InvalidPlanOfferingException('Phase 1 Plan Offering price must use MYR.');
        }

        if ($configurationVersion === '' || trim($configurationVersion) !== $configurationVersion
            || mb_strlen($configurationVersion) > 100) {
            throw new InvalidCommercialCatalogueValueException(
                'Plan Offering configuration version must be a normalized value of at most 100 characters.',
            );
        }

        if ($capabilityConfigurationReference === ''
            || trim($capabilityConfigurationReference) !== $capabilityConfigurationReference
            || mb_strlen($capabilityConfigurationReference) > 100) {
            throw new InvalidCommercialCatalogueValueException(
                'Plan Offering capability configuration reference must be a normalized value of at most 100 characters.',
            );
        }

        if ($displayOrder < 0) {
            throw new InvalidCommercialCatalogueValueException('Plan Offering display order cannot be negative.');
        }

        if ($version < 0) {
            throw new InvalidCommercialCatalogueValueException('Plan Offering version cannot be negative.');
        }

    }

    public function isAvailableForNewPurchase(Plan $plan, BillingOption $billingOption, string $calendarDate): bool
    {
        $this->assertReferences($plan, $billingOption);

        return $this->status === PlanOfferingStatus::Active
            && $this->effectivePeriod->includes($calendarDate)
            && $plan->isAvailableForNewPurchase()
            && $billingOption->isAvailableOn($calendarDate);
    }

    public function isAvailableForRenewal(Plan $plan, BillingOption $billingOption, string $calendarDate): bool
    {
        $this->assertReferences($plan, $billingOption);

        if (! $this->effectivePeriod->includes($calendarDate)
            || ! $plan->isAvailableForRenewal()
            || $billingOption->isNonRecurring()) {
            return false;
        }

        if ($this->status === PlanOfferingStatus::Grandfathered) {
            return true;
        }

        return $this->status === PlanOfferingStatus::Active && $billingOption->isAvailableOn($calendarDate);
    }

    public function activate(): self
    {
        return $this->transitionTo(PlanOfferingStatus::Active, [PlanOfferingStatus::Draft]);
    }

    public function makeUnavailable(): self
    {
        return $this->transitionTo(PlanOfferingStatus::Unavailable, [PlanOfferingStatus::Active]);
    }

    public function grandfather(): self
    {
        return $this->transitionTo(PlanOfferingStatus::Grandfathered, [
            PlanOfferingStatus::Active,
            PlanOfferingStatus::Unavailable,
        ]);
    }

    public function retire(): self
    {
        return $this->transitionTo(PlanOfferingStatus::Retired, [
            PlanOfferingStatus::Draft,
            PlanOfferingStatus::Active,
            PlanOfferingStatus::Unavailable,
            PlanOfferingStatus::Grandfathered,
        ]);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version !== $this->version + 1) {
            throw new InvalidCommercialCatalogueValueException(
                'Plan Offering version must advance exactly one step.',
            );
        }

        $this->version = $version;
    }

    private function assertReferences(Plan $plan, BillingOption $billingOption): void
    {
        if ($plan->id->value !== $this->planId->value || $billingOption->id->value !== $this->billingOptionId->value) {
            throw new InvalidPlanOfferingException(
                'Plan Offering must be evaluated only with its referenced Plan and Billing Option.',
            );
        }
    }

    /** @param list<PlanOfferingStatus> $allowedFrom */
    private function transitionTo(PlanOfferingStatus $status, array $allowedFrom): self
    {
        if (! in_array($this->status, $allowedFrom, true)) {
            throw new InvalidPlanOfferingException(sprintf(
                'Plan Offering cannot transition from %s to %s.',
                $this->status->value,
                $status->value,
            ));
        }

        return new self(
            $this->id,
            $this->planId,
            $this->billingOptionId,
            $this->currentPrice,
            $this->effectivePeriod,
            $status,
            $this->configurationVersion,
            $this->capabilityConfigurationReference,
            $this->displayOrder,
            $this->version,
        );
    }
}
