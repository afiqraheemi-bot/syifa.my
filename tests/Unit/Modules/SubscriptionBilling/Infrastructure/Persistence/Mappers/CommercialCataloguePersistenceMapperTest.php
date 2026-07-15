<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers;

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
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\RecurrenceClassification;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CommercialCataloguePersistenceMapperTest extends TestCase
{
    public function test_it_round_trips_plan_version_metadata(): void
    {
        $mapper = new CommercialCataloguePersistenceMapper;
        $plan = new Plan(
            new PlanId('00000000-0000-4000-8000-000000000101'),
            new PlanName('Syifa Managed Website'),
            new PlanCode('syifa_managed_website'),
            'Managed website subscription for an eligible clinic.',
            PlanLifecycle::draft(),
            1,
            new DateTimeImmutable('2026-07-15T00:00:00+00:00'),
            new DateTimeImmutable('2026-07-15T00:00:00+00:00'),
        );
        $plan->synchronizeVersion(1);

        $record = $mapper->planRecord($plan);
        self::assertSame(1, $record->version);

        $reconstituted = $mapper->toPlanDomain($record);
        self::assertSame(1, $reconstituted->version());
        self::assertSame($plan->code->value, $reconstituted->code->value);
    }

    public function test_it_round_trips_the_other_catalogue_objects_without_losing_version_metadata(): void
    {
        $mapper = new CommercialCataloguePersistenceMapper;

        $billingOption = new BillingOption(
            new BillingOptionId('00000000-0000-4000-8000-000000000102'),
            new BillingOptionCode('monthly'),
            new BillingOptionName('Monthly'),
            CatalogueAvailability::Available,
            RecurrenceClassification::Recurring,
            new BillingDuration(BillingInterval::Month, 1),
            new EffectivePeriod('2026-07-01'),
            1,
        );
        $billingOption->synchronizeVersion(1);
        self::assertSame(1, $mapper->toBillingOptionDomain($mapper->billingOptionRecord($billingOption))->version());

        $planOffering = new PlanOffering(
            new PlanOfferingId('00000000-0000-4000-8000-000000000103'),
            new PlanId('00000000-0000-4000-8000-000000000101'),
            new BillingOptionId('00000000-0000-4000-8000-000000000102'),
            new Money(12500, 'MYR'),
            new EffectivePeriod('2026-07-01'),
            PlanOfferingStatus::Draft,
            'catalogue-v1',
            'capability-package-v1',
            1,
        );
        $planOffering->synchronizeVersion(1);
        self::assertSame(1, $mapper->toPlanOfferingDomain($mapper->planOfferingRecord($planOffering))->version());

        $capability = new CapabilityDefinition(
            new CapabilityId('00000000-0000-4000-8000-000000000104'),
            new CapabilityKey('configured_capability'),
            'Configured capability',
            'Describes the product feature.',
            'Unlocks one governed commercial feature.',
            CapabilityStatus::Draft,
        );
        $capability->synchronizeVersion(1);
        self::assertSame(1, $mapper->toCapabilityDefinitionDomain($mapper->capabilityDefinitionRecord($capability))->version());
    }
}
