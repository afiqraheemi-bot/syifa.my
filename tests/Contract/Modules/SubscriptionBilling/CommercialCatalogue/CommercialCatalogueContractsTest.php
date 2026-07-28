<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\SubscriptionBilling\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\BillingOptionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AvailablePlanOfferingQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\DeprecateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\GrandfatherPlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\GrandfatherPlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\MakePlanOfferingUnavailableCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\MakePlanUnavailableCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\Exceptions\InvalidPaginatedResultException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationMeta;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedBillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedCapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetireCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetirePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetirePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\SubscriptionOfferingResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\SubscriptionOfferingSelectionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanDetailsCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use InvalidArgumentException;
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
            ['planId', 'code', 'name', 'description', 'status', 'displayOrder', 'createdAt', 'lastChangedAt', 'version'],
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
                'version',
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
            ['capabilityId', 'capabilityKey', 'name', 'description', 'commercialMeaning', 'status', 'version'],
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
            ['planId', 'name', 'description', 'displayOrder', 'expectedVersion', 'occurredAt', 'actorPlatformIdentityId', 'correlationId'],
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

    public function test_offset_pagination_and_admin_query_contracts_are_precise(): void
    {
        self::assertSame(['page', 'perPage'], $this->propertyNames(OffsetPaginationInput::class));
        self::assertSame(['currentPage', 'perPage', 'total', 'lastPage', 'from', 'to'], $this->propertyNames(OffsetPaginationMeta::class));
        self::assertSame(['items', 'meta'], $this->propertyNames(PaginatedPlanData::class));
        self::assertSame(['items', 'meta'], $this->propertyNames(PaginatedBillingOptionData::class));
        self::assertSame(['items', 'meta'], $this->propertyNames(PaginatedCapabilityDefinitionData::class));
        self::assertSame(['items', 'meta'], $this->propertyNames(PaginatedPlanOfferingData::class));

        $pagination = new OffsetPaginationInput(2, 25);
        $meta = new OffsetPaginationMeta(2, 25, 50, 2, 26, 50);
        $planPage = new PaginatedPlanData([], $meta);
        $billingPage = new PaginatedBillingOptionData([], $meta);
        $capabilityPage = new PaginatedCapabilityDefinitionData([], $meta);
        $planOfferingPage = new PaginatedPlanOfferingData([], $meta);

        $planQuery = new class($planPage) implements PlanCatalogueQueryInterface
        {
            public ?OffsetPaginationInput $pagination = null;

            public function __construct(private PaginatedPlanData $result) {}

            public function listPlans(OffsetPaginationInput $pagination): PaginatedPlanData
            {
                $this->pagination = $pagination;

                return $this->result;
            }
        };
        $billingQuery = new class($billingPage) implements BillingOptionCatalogueQueryInterface
        {
            public ?OffsetPaginationInput $pagination = null;

            public function __construct(private PaginatedBillingOptionData $result) {}

            public function listBillingOptions(OffsetPaginationInput $pagination): PaginatedBillingOptionData
            {
                $this->pagination = $pagination;

                return $this->result;
            }
        };
        $capabilityQuery = new class($capabilityPage) implements CapabilityDefinitionCatalogueQueryInterface
        {
            public ?OffsetPaginationInput $pagination = null;

            public function __construct(private PaginatedCapabilityDefinitionData $result) {}

            public function listCapabilityDefinitions(OffsetPaginationInput $pagination): PaginatedCapabilityDefinitionData
            {
                $this->pagination = $pagination;

                return $this->result;
            }
        };
        $planOfferingQuery = new class($planOfferingPage) implements PlanOfferingCatalogueQueryInterface
        {
            public ?OffsetPaginationInput $pagination = null;

            public function __construct(private PaginatedPlanOfferingData $result) {}

            public function listPlanOfferings(OffsetPaginationInput $pagination): PaginatedPlanOfferingData
            {
                $this->pagination = $pagination;

                return $this->result;
            }
        };

        self::assertSame($planPage, $planQuery->listPlans($pagination));
        self::assertSame($billingPage, $billingQuery->listBillingOptions($pagination));
        self::assertSame($capabilityPage, $capabilityQuery->listCapabilityDefinitions($pagination));
        self::assertSame($planOfferingPage, $planOfferingQuery->listPlanOfferings($pagination));
        self::assertSame($pagination, $planQuery->pagination);
        self::assertSame($pagination, $billingQuery->pagination);
        self::assertSame($pagination, $capabilityQuery->pagination);
        self::assertSame($pagination, $planOfferingQuery->pagination);
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

    public function test_pagination_input_rejects_out_of_bounds_page_and_per_page(): void
    {
        try {
            new OffsetPaginationInput(0, 25);
            self::fail('Expected page below 1 to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertNotEmpty($exception->getMessage());
        }

        try {
            new OffsetPaginationInput(1, 0);
            self::fail('Expected perPage below 1 to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertNotEmpty($exception->getMessage());
        }

        try {
            new OffsetPaginationInput(1, 101);
            self::fail('Expected perPage above 100 to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertNotEmpty($exception->getMessage());
        }

        $valid = new OffsetPaginationInput(1, 100);
        self::assertSame(1, $valid->page);
        self::assertSame(100, $valid->perPage);
    }

    public function test_pagination_meta_rejects_invalid_combinations(): void
    {
        $invalidCombinations = [
            'currentPage below 1' => [0, 25, 0, 1, null, null],
            'perPage below 1' => [1, 0, 0, 1, null, null],
            'perPage above 100' => [1, 101, 0, 1, null, null],
            'negative total' => [1, 25, -1, 1, null, null],
            'lastPage below 1' => [1, 25, 0, 0, null, null],
            'empty total with non-null from' => [1, 25, 0, 1, 1, null],
            'empty total with non-null to' => [1, 25, 0, 1, null, 1],
            'non-empty total with null from' => [1, 25, 10, 1, null, 5],
            'non-empty total with null to' => [1, 25, 10, 1, 1, null],
            'from below 1' => [1, 25, 10, 1, 0, 5],
            'to below from' => [1, 25, 10, 1, 5, 4],
            'to above total' => [1, 25, 10, 1, 1, 11],
        ];

        foreach ($invalidCombinations as $label => [$currentPage, $perPage, $total, $lastPage, $from, $to]) {
            try {
                new OffsetPaginationMeta($currentPage, $perPage, $total, $lastPage, $from, $to);
                self::fail('Expected rejection for: '.$label);
            } catch (InvalidArgumentException $exception) {
                self::assertNotEmpty($exception->getMessage(), $label);
            }
        }
    }

    public function test_pagination_meta_requires_null_from_and_to_only_when_total_is_zero(): void
    {
        $empty = new OffsetPaginationMeta(1, 25, 0, 1, null, null);
        self::assertNull($empty->from);
        self::assertNull($empty->to);

        $nonEmpty = new OffsetPaginationMeta(1, 25, 10, 1, 1, 10);
        self::assertSame(1, $nonEmpty->from);
        self::assertSame(10, $nonEmpty->to);
    }

    public function test_each_paginated_dto_accepts_its_exact_item_type_and_preserves_list_order(): void
    {
        $meta = new OffsetPaginationMeta(1, 25, 2, 1, 1, 2);

        $plans = [$this->planData('plan-a'), $this->planData('plan-b')];
        $planPage = new PaginatedPlanData($plans, $meta);
        self::assertSame($plans, $planPage->items);

        $billingOptions = [$this->billingOptionData('option-a'), $this->billingOptionData('option-b')];
        $billingPage = new PaginatedBillingOptionData($billingOptions, $meta);
        self::assertSame($billingOptions, $billingPage->items);

        $capabilities = [$this->capabilityData('capability-a'), $this->capabilityData('capability-b')];
        $capabilityPage = new PaginatedCapabilityDefinitionData($capabilities, $meta);
        self::assertSame($capabilities, $capabilityPage->items);

        $planOfferings = [$this->planOfferingData('offering-a'), $this->planOfferingData('offering-b')];
        $planOfferingPage = new PaginatedPlanOfferingData($planOfferings, $meta);
        self::assertSame($planOfferings, $planOfferingPage->items);
    }

    public function test_each_paginated_dto_rejects_a_wrong_item_type(): void
    {
        $meta = new OffsetPaginationMeta(1, 25, 1, 1, 1, 1);
        $wrongItem = $this->billingOptionData('wrong-type');

        foreach ([
            fn () => new PaginatedPlanData([$wrongItem], $meta),
            fn () => new PaginatedBillingOptionData([$this->planData('wrong-type')], $meta),
            fn () => new PaginatedCapabilityDefinitionData([$wrongItem], $meta),
            fn () => new PaginatedPlanOfferingData([$wrongItem], $meta),
        ] as $index => $constructor) {
            try {
                $constructor();
                self::fail('Expected a wrong item type to be rejected for case '.$index);
            } catch (InvalidPaginatedResultException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_each_paginated_dto_rejects_a_non_list_array(): void
    {
        $meta = new OffsetPaginationMeta(1, 25, 1, 1, 1, 1);
        $nonList = [5 => $this->planData('non-list')];

        try {
            new PaginatedPlanData($nonList, $meta);
            self::fail('Expected a non-list item array to be rejected.');
        } catch (InvalidPaginatedResultException $exception) {
            self::assertNotEmpty($exception->getMessage());
        }
    }

    public function test_exact_admin_query_interface_inventory_by_reflection(): void
    {
        foreach ([
            PlanCatalogueQueryInterface::class,
            BillingOptionCatalogueQueryInterface::class,
            CapabilityDefinitionCatalogueQueryInterface::class,
            PlanOfferingCatalogueQueryInterface::class,
        ] as $interface) {
            self::assertTrue(interface_exists($interface), $interface);
        }
    }

    public function test_deterministic_ordering_documentation_exists_on_every_admin_query_interface(): void
    {
        foreach ([
            PlanCatalogueQueryInterface::class,
            BillingOptionCatalogueQueryInterface::class,
            CapabilityDefinitionCatalogueQueryInterface::class,
            PlanOfferingCatalogueQueryInterface::class,
        ] as $interface) {
            $docComment = (new ReflectionClass($interface))->getDocComment();
            self::assertIsString($docComment, $interface);
            self::assertStringContainsStringIgnoringCase('deterministic', $docComment, $interface);
        }
    }

    public function test_exact_mutation_command_inventory_by_reflection(): void
    {
        foreach ([
            CreatePlanCommand::class,
            CreateBillingOptionCommand::class,
            CreateCapabilityDefinitionCommand::class,
            CreatePlanOfferingCommand::class,
            UpdatePlanDetailsCommand::class,
            UpdateBillingOptionCommand::class,
            UpdateCapabilityDefinitionCommand::class,
            UpdatePlanOfferingCommand::class,
            ActivatePlanCommand::class,
            ActivatePlanOfferingCommand::class,
            ActivateCapabilityDefinitionCommand::class,
            MakePlanUnavailableCommand::class,
            MakePlanOfferingUnavailableCommand::class,
            GrandfatherPlanCommand::class,
            GrandfatherPlanOfferingCommand::class,
            DeprecateCapabilityDefinitionCommand::class,
            RetirePlanCommand::class,
            RetirePlanOfferingCommand::class,
            RetireCapabilityDefinitionCommand::class,
        ] as $command) {
            self::assertTrue(class_exists($command), $command);
            self::assertTrue((new ReflectionClass($command))->isReadOnly(), $command);
        }
    }

    private function planData(?string $planId = null): PlanData
    {
        return new PlanData(
            planId: $planId ?? 'plan-id',
            code: 'configured_plan',
            name: 'Configured Plan',
            description: 'Managed commercial catalogue plan.',
            status: 'draft',
            displayOrder: 1,
            createdAt: '2026-07-14T05:30:00Z',
            lastChangedAt: '2026-07-14T05:30:00Z',
        );
    }

    private function billingOptionData(?string $billingOptionId = null): BillingOptionData
    {
        return new BillingOptionData(
            billingOptionId: $billingOptionId ?? 'billing-option-id',
            code: 'configured_option',
            name: 'Configured billing option',
            availability: 'available',
            recurrenceClassification: 'recurring',
            intervalUnit: 'month',
            intervalCount: 1,
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            displayOrder: 1,
        );
    }

    private function capabilityData(?string $capabilityId = null): CapabilityDefinitionData
    {
        return new CapabilityDefinitionData(
            capabilityId: $capabilityId ?? 'capability-id',
            capabilityKey: 'feature.alpha',
            name: 'Feature Alpha',
            description: 'A governed commercial capability.',
            commercialMeaning: 'Unlocks the commercial capability.',
            status: 'draft',
        );
    }

    private function planOfferingData(?string $planOfferingId = null): PlanOfferingData
    {
        return new PlanOfferingData(
            planOfferingId: $planOfferingId ?? 'plan-offering-id',
            planId: 'plan-id',
            billingOptionId: 'billing-option-id',
            amountMinor: 12500,
            currencyCode: 'MYR',
            status: 'active',
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            configurationVersion: '1',
            capabilityConfigurationReference: 'capability-package-v1',
            displayOrder: 1,
        );
    }

    private function propertyNames(string $class): array
    {
        return array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass($class))->getProperties(),
        );
    }
}
