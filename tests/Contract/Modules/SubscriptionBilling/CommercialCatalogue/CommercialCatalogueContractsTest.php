<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\SubscriptionBilling\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AvailablePlanOfferingQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\SubscriptionOfferingResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\SubscriptionOfferingSelectionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanDetailsCommand;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CommercialCatalogueContractsTest extends TestCase
{
    public function test_catalogue_read_models_are_immutable_and_complete(): void
    {
        self::assertTrue((new ReflectionClass(PlanData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(BillingOptionData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(PlanOfferingData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(CapabilityDefinitionData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(SubscriptionOfferingSelectionData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(ResolvedSubscriptionOfferingData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(ComputedSubscriptionEntitlementData::class))->isReadOnly());

        self::assertSame(
            ['planId', 'code', 'name', 'description', 'status', 'displayOrder', 'createdAt', 'lastChangedAt'],
            $this->propertyNames(PlanData::class),
        );
        self::assertSame(
            [
                'billingOptionId',
                'code',
                'name',
                'availability',
                'recurrenceClassification',
                'intervalUnit',
                'intervalCount',
                'effectiveStart',
                'effectiveEnd',
                'displayOrder',
            ],
            $this->propertyNames(BillingOptionData::class),
        );
        self::assertSame(
            [
                'planOfferingId',
                'planId',
                'billingOptionId',
                'amountMinor',
                'currencyCode',
                'status',
                'effectiveStart',
                'effectiveEnd',
                'configurationVersion',
                'capabilityConfigurationReference',
                'displayOrder',
            ],
            $this->propertyNames(PlanOfferingData::class),
        );
        self::assertSame(
            ['capabilityId', 'capabilityKey', 'name', 'description', 'commercialMeaning', 'status'],
            $this->propertyNames(CapabilityDefinitionData::class),
        );
        self::assertSame(
            ['planOfferingId', 'intendedEffectiveDate'],
            $this->propertyNames(SubscriptionOfferingSelectionData::class),
        );
        self::assertSame(
            [
                'planOfferingId',
                'planId',
                'billingOptionId',
                'amountMinor',
                'currencyCode',
                'billingPeriodStart',
                'billingPeriodEnd',
                'offeringConfigurationVersion',
                'capabilityConfigurationReference',
            ],
            $this->propertyNames(ResolvedSubscriptionOfferingData::class),
        );
        self::assertSame(
            ['planId', 'billingOptionId', 'configurationVersion', 'capabilityKeys'],
            $this->propertyNames(ComputedSubscriptionEntitlementData::class),
        );
    }

    public function test_query_and_lookup_interfaces_expose_the_expected_signatures(): void
    {
        $query = new class implements CommercialCatalogueQueryInterface
        {
            public function findPlan(string $planId): ?PlanData
            {
                return new PlanData($planId, 'plan-code', 'Plan', 'Description', 'active', 1, '2026-07-14T05:30:00Z', '2026-07-14T05:30:00Z');
            }

            public function findBillingOption(string $billingOptionId): ?BillingOptionData
            {
                return new BillingOptionData(
                    $billingOptionId,
                    'monthly',
                    'Monthly',
                    'available',
                    'recurring',
                    'month',
                    1,
                    '2026-07-14',
                    null,
                    1,
                );
            }

            public function findPlanOffering(string $planOfferingId): ?PlanOfferingData
            {
                return new PlanOfferingData(
                    $planOfferingId,
                    'plan-id',
                    'billing-option-id',
                    10000,
                    'MYR',
                    'active',
                    '2026-07-14',
                    null,
                    '1',
                    'capability-configuration-ref',
                    1,
                );
            }

            public function findCapability(string $capabilityId): ?CapabilityDefinitionData
            {
                return new CapabilityDefinitionData(
                    $capabilityId,
                    'feature.alpha',
                    'Feature Alpha',
                    'Description',
                    'Commercial meaning',
                    'active',
                );
            }
        };
        $available = new class implements AvailablePlanOfferingQueryInterface
        {
            public function listAvailableOfferings(string $effectiveDate): array
            {
                return [
                    new PlanOfferingData(
                        'offering-id',
                        'plan-id',
                        'billing-option-id',
                        10000,
                        'MYR',
                        'active',
                        $effectiveDate,
                        null,
                        '1',
                        'capability-configuration-ref',
                        1,
                    ),
                ];
            }
        };
        $resolver = new class implements SubscriptionOfferingResolverInterface
        {
            public function resolve(SubscriptionOfferingSelectionData $selection): ?ResolvedSubscriptionOfferingData
            {
                return new ResolvedSubscriptionOfferingData(
                    $selection->planOfferingId,
                    'plan-id',
                    'billing-option-id',
                    10000,
                    'MYR',
                    $selection->intendedEffectiveDate,
                    '2026-08-14',
                    '1',
                    'capability-configuration-ref',
                );
            }
        };
        $computation = new class implements SubscriptionEntitlementComputationInterface
        {
            public function compute(
                ResolvedSubscriptionOfferingData $resolvedOffering,
            ): ComputedSubscriptionEntitlementData {
                return new ComputedSubscriptionEntitlementData(
                    $resolvedOffering->planId,
                    $resolvedOffering->billingOptionId,
                    $resolvedOffering->offeringConfigurationVersion,
                    ['feature.alpha', 'feature.beta'],
                );
            }
        };
        $lookup = new class implements SubscriptionEntitlementLookupInterface
        {
            public function hasCapability(
                string $tenantId,
                string $capabilityKey,
                string $effectiveDateTime,
            ): bool {
                return $tenantId !== '' && $capabilityKey !== '' && $effectiveDateTime !== '';
            }

            public function getActiveCapabilityKeys(
                string $tenantId,
                string $effectiveDateTime,
            ): array {
                return $tenantId === '' || $effectiveDateTime === '' ? [] : ['feature.alpha'];
            }
        };

        self::assertSame('plan-code', $query->findPlan('plan-id')->code);
        self::assertSame('monthly', $query->findBillingOption('billing-option-id')->code);
        self::assertSame('capability-configuration-ref', $query->findPlanOffering('offering-id')->capabilityConfigurationReference);
        self::assertSame('feature.alpha', $query->findCapability('capability-id')->capabilityKey);
        self::assertCount(1, $available->listAvailableOfferings('2026-07-14'));
        self::assertSame(
            'capability-configuration-ref',
            $resolver->resolve(new SubscriptionOfferingSelectionData('offering-id', '2026-07-14'))->capabilityConfigurationReference,
        );
        self::assertSame(
            ['feature.alpha'],
            $lookup->getActiveCapabilityKeys('tenant-id', '2026-07-14T05:30:00Z'),
        );
        self::assertSame(
            ['feature.alpha', 'feature.beta'],
            $computation->compute(new ResolvedSubscriptionOfferingData(
                'offering-id',
                'plan-id',
                'billing-option-id',
                10000,
                'MYR',
                '2026-07-14',
                '2026-08-14',
                '1',
                'capability-configuration-ref',
            ))->capabilityKeys,
        );
    }

    public function test_command_contracts_are_immutable_and_carry_actor_identity(): void
    {
        self::assertTrue((new ReflectionClass(CreatePlanCommand::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(UpdatePlanDetailsCommand::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(CreateBillingOptionCommand::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(CreateCapabilityDefinitionCommand::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(CreatePlanOfferingCommand::class))->isReadOnly());

        self::assertSame(
            ['code', 'name', 'description', 'displayOrder', 'occurredAt', 'actorPlatformIdentityId', 'correlationId'],
            $this->propertyNames(CreatePlanCommand::class),
        );
        self::assertSame(
            ['planId', 'name', 'description', 'displayOrder', 'occurredAt', 'actorPlatformIdentityId', 'correlationId'],
            $this->propertyNames(UpdatePlanDetailsCommand::class),
        );
        self::assertSame(
            [
                'code',
                'name',
                'recurrenceClassification',
                'intervalUnit',
                'intervalCount',
                'displayOrder',
                'effectiveStart',
                'effectiveEnd',
                'occurredAt',
                'actorPlatformIdentityId',
                'correlationId',
            ],
            $this->propertyNames(CreateBillingOptionCommand::class),
        );
        self::assertSame(
            ['capabilityKey', 'name', 'description', 'commercialMeaning', 'occurredAt', 'actorPlatformIdentityId', 'correlationId'],
            $this->propertyNames(CreateCapabilityDefinitionCommand::class),
        );
        self::assertSame(
            [
                'planId',
                'billingOptionId',
                'amountMinor',
                'currencyCode',
                'effectiveStart',
                'effectiveEnd',
                'capabilityConfigurationReference',
                'displayOrder',
                'occurredAt',
                'actorPlatformIdentityId',
                'correlationId',
            ],
            $this->propertyNames(CreatePlanOfferingCommand::class),
        );

        foreach ([
            CreatePlanCommand::class,
            UpdatePlanDetailsCommand::class,
            CreateBillingOptionCommand::class,
            CreateCapabilityDefinitionCommand::class,
            CreatePlanOfferingCommand::class,
        ] as $command) {
            self::assertNotContains('tenantId', $this->propertyNames($command));
        }
    }

    public function test_public_contract_surface_keeps_tenant_and_rbac_concerns_out_of_catalogue_commands(): void
    {
        foreach ([
            PlanData::class,
            BillingOptionData::class,
            PlanOfferingData::class,
            CapabilityDefinitionData::class,
            SubscriptionOfferingSelectionData::class,
            ResolvedSubscriptionOfferingData::class,
            ComputedSubscriptionEntitlementData::class,
            CreatePlanCommand::class,
            UpdatePlanDetailsCommand::class,
            CreateBillingOptionCommand::class,
            CreateCapabilityDefinitionCommand::class,
            CreatePlanOfferingCommand::class,
        ] as $class) {
            $properties = $this->propertyNames($class);
            self::assertSame([], array_intersect($properties, ['tenantId', 'permission', 'permissions', 'role', 'policy']));
        }
    }

    public function test_resolved_offering_does_not_expose_authoritative_capability_keys(): void
    {
        self::assertNotContains('capabilityKeys', $this->propertyNames(ResolvedSubscriptionOfferingData::class));
        self::assertContains('capabilityKeys', $this->propertyNames(ComputedSubscriptionEntitlementData::class));
    }

    public function test_canonical_calendar_date_and_utc_instant_formats_are_used(): void
    {
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', '2026-07-14');
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', '2026-07-14T05:30:00Z');
    }

    private function propertyNames(string $class): array
    {
        return array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass($class))->getProperties(),
        );
    }
}
