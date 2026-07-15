<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingDuration;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingInterval;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionName;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CatalogueAvailability;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\EffectivePeriod;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanLifecycle;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanName;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\RecurrenceClassification;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\InvalidCommercialCatalogueStorageStateException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\BillingOptionStorageRecord;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\CapabilityDefinitionStorageRecord;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\PlanOfferingStorageRecord;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\PlanStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;

final class CommercialCataloguePersistenceMapper
{
    public function planRecord(Plan $plan): PlanStorageRecord
    {
        return new PlanStorageRecord(
            $plan->id->value,
            $plan->code->value,
            $plan->name->value,
            $plan->description,
            $plan->lifecycle->status->value,
            $plan->displayOrder,
            $plan->createdAt,
            $plan->lastChangedAt,
            $plan->version(),
        );
    }

    public function toPlanDomain(PlanStorageRecord $record): Plan
    {
        return new Plan(
            new PlanId($record->id),
            new PlanName($record->name),
            new PlanCode($record->code),
            $record->description,
            new PlanLifecycle($this->planStatus($record->status)),
            $record->displayOrder,
            $record->createdAt,
            $record->lastChangedAt,
            $record->version,
        );
    }

    public function billingOptionRecord(BillingOption $billingOption): BillingOptionStorageRecord
    {
        return new BillingOptionStorageRecord(
            $billingOption->id->value,
            $billingOption->code->value,
            $billingOption->name->value,
            $billingOption->availability->value,
            $billingOption->recurrence->value,
            $billingOption->duration?->interval->value,
            $billingOption->duration?->intervalCount,
            $this->date($billingOption->effectivePeriod->startsOn),
            $billingOption->effectivePeriod->endsOn === null
                ? null
                : $this->date($billingOption->effectivePeriod->endsOn),
            $billingOption->displayOrder,
            $billingOption->version(),
        );
    }

    public function toBillingOptionDomain(BillingOptionStorageRecord $record): BillingOption
    {
        $duration = null;

        if ($record->intervalUnit !== null || $record->intervalCount !== null) {
            if ($record->intervalUnit === null || $record->intervalCount === null) {
                throw new InvalidCommercialCatalogueStorageStateException(
                    'Billing Option duration storage must include both interval unit and interval count.',
                );
            }

            $duration = new BillingDuration(BillingInterval::from($record->intervalUnit), $record->intervalCount);
        }

        return new BillingOption(
            new BillingOptionId($record->id),
            new BillingOptionCode($record->code),
            new BillingOptionName($record->name),
            CatalogueAvailability::from($record->availability),
            RecurrenceClassification::from($record->recurrenceClassification),
            $duration,
            new EffectivePeriod($this->calendarDate($record->effectiveStart), $record->effectiveEnd === null ? null : $this->calendarDate($record->effectiveEnd)),
            $record->displayOrder,
            $record->version,
        );
    }

    public function planOfferingRecord(PlanOffering $planOffering): PlanOfferingStorageRecord
    {
        return new PlanOfferingStorageRecord(
            $planOffering->id->value,
            $planOffering->planId->value,
            $planOffering->billingOptionId->value,
            $planOffering->currentPrice->amountMinor,
            $planOffering->currentPrice->currencyCode,
            $planOffering->status->value,
            $this->date($planOffering->effectivePeriod->startsOn),
            $planOffering->effectivePeriod->endsOn === null
                ? null
                : $this->date($planOffering->effectivePeriod->endsOn),
            $planOffering->configurationVersion,
            $planOffering->capabilityConfigurationReference,
            $planOffering->displayOrder,
            $planOffering->version(),
        );
    }

    public function toPlanOfferingDomain(PlanOfferingStorageRecord $record): PlanOffering
    {
        return new PlanOffering(
            new PlanOfferingId($record->id),
            new PlanId($record->planId),
            new BillingOptionId($record->billingOptionId),
            new Money($record->amountMinor, $record->currencyCode),
            new EffectivePeriod($this->calendarDate($record->effectiveStart), $record->effectiveEnd === null ? null : $this->calendarDate($record->effectiveEnd)),
            PlanOfferingStatus::from($record->status),
            $record->configurationVersion,
            $record->capabilityConfigurationReference,
            $record->displayOrder,
            $record->version,
        );
    }

    public function capabilityDefinitionRecord(CapabilityDefinition $capabilityDefinition): CapabilityDefinitionStorageRecord
    {
        return new CapabilityDefinitionStorageRecord(
            $capabilityDefinition->id->value,
            $capabilityDefinition->key->value,
            $capabilityDefinition->name,
            $capabilityDefinition->description,
            $capabilityDefinition->commercialMeaning,
            $capabilityDefinition->status->value,
            $capabilityDefinition->version(),
        );
    }

    public function toCapabilityDefinitionDomain(CapabilityDefinitionStorageRecord $record): CapabilityDefinition
    {
        return new CapabilityDefinition(
            new CapabilityId($record->id),
            new CapabilityKey($record->key),
            $record->name,
            $record->description,
            $record->commercialMeaning,
            CapabilityStatus::from($record->status),
            $record->version,
        );
    }

    private function planStatus(string $status): PlanStatus
    {
        return PlanStatus::from($status);
    }

    private function date(DateTimeInterface|string $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable($value);
    }

    private function calendarDate(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d');
    }
}
