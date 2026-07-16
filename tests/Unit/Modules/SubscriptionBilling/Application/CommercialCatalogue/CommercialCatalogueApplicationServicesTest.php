<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CommercialCatalogueIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ComputeSubscriptionEntitlementService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\DeprecateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GrandfatherPlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GrandfatherPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListAvailableSubscriptionOfferingsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListBillingOptionsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListCapabilityDefinitionsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListPlanOfferingsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListPlansService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\MakePlanOfferingUnavailableService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\MakePlanUnavailableService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ResolveSubscriptionOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetireCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdateBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanDetailsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanOfferingService;
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
use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanLifecycleTransitionException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanOfferingException;
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
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StaleCommercialCatalogueWriteException;
use ArrayIterator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Throwable;
use Traversable;

final class CommercialCatalogueApplicationServicesTest extends TestCase
{
    public function test_create_services_save_once_and_return_versioned_domain_objects(): void
    {
        $identifierGenerator = new RecordingIdentifierGenerator([
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            $this->uuid(4),
        ]);

        $planRepository = new RecordingPlanRepository;
        $billingOptionRepository = new RecordingBillingOptionRepository;
        $capabilityRepository = new RecordingCapabilityDefinitionRepository;
        $planOfferingRepository = new RecordingPlanOfferingRepository;

        $plan = (new CreatePlanService($identifierGenerator, $planRepository))->execute($this->createPlanCommand());
        $billingOption = (new CreateBillingOptionService($identifierGenerator, $billingOptionRepository))->execute($this->createBillingOptionCommand());
        $capability = (new CreateCapabilityDefinitionService($identifierGenerator, $capabilityRepository))->execute($this->createCapabilityCommand());
        $planOffering = (new CreatePlanOfferingService($identifierGenerator, $planOfferingRepository))->execute(
            $this->createPlanOfferingCommand($plan->id->value, $billingOption->id->value),
        );

        self::assertSame(1, $planRepository->saveCalls);
        self::assertSame(1, $billingOptionRepository->saveCalls);
        self::assertSame(1, $capabilityRepository->saveCalls);
        self::assertSame(1, $planOfferingRepository->saveCalls);

        self::assertSame(1, $plan->version());
        self::assertSame(1, $billingOption->version());
        self::assertSame(1, $capability->version());
        self::assertSame(1, $planOffering->version());
        self::assertSame('1', $planOffering->configurationVersion);
        self::assertSame(PlanStatus::Draft, $plan->lifecycle->status);
        self::assertSame(CatalogueAvailability::Available, $billingOption->availability);
        self::assertSame(CapabilityStatus::Draft, $capability->status);
        self::assertSame(PlanOfferingStatus::Draft, $planOffering->status);
    }

    public function test_query_services_delegate_to_the_approved_query_ports(): void
    {
        $pagination = new OffsetPaginationInput(page: 2, perPage: 25);

        $planPage = new PaginatedPlanData(
            [$this->planData(), $this->planData(planId: $this->uuid(91))],
            new OffsetPaginationMeta(2, 25, 50, 2, 26, 50),
        );
        $billingOptionPage = new PaginatedBillingOptionData(
            [$this->billingOptionData(), $this->billingOptionData(billingOptionId: $this->uuid(92))],
            new OffsetPaginationMeta(2, 25, 50, 2, 26, 50),
        );
        $capabilityPage = new PaginatedCapabilityDefinitionData(
            [$this->capabilityData(), $this->capabilityData(capabilityId: $this->uuid(93))],
            new OffsetPaginationMeta(2, 25, 50, 2, 26, 50),
        );
        $planOfferingPage = new PaginatedPlanOfferingData(
            [$this->planOfferingData(), $this->planOfferingData(planOfferingId: $this->uuid(94))],
            new OffsetPaginationMeta(2, 25, 50, 2, 26, 50),
        );

        $planQuery = new RecordingPlanCatalogueQuery($planPage);
        $billingQuery = new RecordingBillingOptionCatalogueQuery($billingOptionPage);
        $capabilityQuery = new RecordingCapabilityDefinitionCatalogueQuery($capabilityPage);
        $planOfferingQuery = new RecordingPlanOfferingCatalogueQuery($planOfferingPage);

        self::assertSame($planPage, (new ListPlansService($planQuery))->execute($pagination));
        self::assertSame($billingOptionPage, (new ListBillingOptionsService($billingQuery))->execute($pagination));
        self::assertSame($capabilityPage, (new ListCapabilityDefinitionsService($capabilityQuery))->execute($pagination));
        self::assertSame($planOfferingPage, (new ListPlanOfferingsService($planOfferingQuery))->execute($pagination));

        self::assertSame($pagination, $planQuery->receivedPagination);
        self::assertSame($pagination, $billingQuery->receivedPagination);
        self::assertSame($pagination, $capabilityQuery->receivedPagination);
        self::assertSame($pagination, $planOfferingQuery->receivedPagination);

        $plan = $this->planData();
        $billingOption = $this->billingOptionData();
        $capability = $this->capabilityData();
        $planOffering = $this->planOfferingData();

        self::assertSame($plan, (new GetPlanService(new RecordingCommercialCatalogueQuery(plan: $plan)))->execute($plan->planId));
        self::assertSame($billingOption, (new GetBillingOptionService(new RecordingCommercialCatalogueQuery(billingOption: $billingOption)))->execute($billingOption->billingOptionId));
        self::assertSame($capability, (new GetCapabilityDefinitionService(new RecordingCommercialCatalogueQuery(capability: $capability)))->execute($capability->capabilityId));
        self::assertSame($planOffering, (new GetPlanOfferingService(new RecordingCommercialCatalogueQuery(planOffering: $planOffering)))->execute($planOffering->planOfferingId));

        $availableOfferings = [$this->planOfferingData(), $this->planOfferingData(planOfferingId: $this->uuid(95))];
        $resolvedOffering = $this->resolvedOfferingData();
        $computedEntitlement = $this->computedEntitlementData();

        self::assertSame(
            $availableOfferings,
            (new ListAvailableSubscriptionOfferingsService(new RecordingAvailablePlanOfferingQuery($availableOfferings)))->execute('2026-07-14'),
        );
        self::assertSame(
            $resolvedOffering,
            (new ResolveSubscriptionOfferingService(new RecordingSubscriptionOfferingResolver($resolvedOffering)))->execute(
                new SubscriptionOfferingSelectionData($resolvedOffering->planOfferingId, '2026-07-14'),
            ),
        );
        self::assertSame(
            $computedEntitlement,
            (new ComputeSubscriptionEntitlementService(new RecordingSubscriptionEntitlementComputation($computedEntitlement)))->execute($resolvedOffering),
        );
    }

    public function test_update_services_load_check_version_save_and_return_updated_objects(): void
    {
        $plan = $this->plan(version: 1);
        $billingOption = $this->billingOption(version: 1);
        $capability = $this->capability(version: 1);
        $planOffering = $this->planOffering(version: 1);

        $planRepository = new RecordingPlanRepository($plan);
        $billingOptionRepository = new RecordingBillingOptionRepository($billingOption);
        $capabilityRepository = new RecordingCapabilityDefinitionRepository($capability);
        $planOfferingRepository = new RecordingPlanOfferingRepository($planOffering);

        $updatedPlan = (new UpdatePlanDetailsService($planRepository))->execute(
            new UpdatePlanDetailsCommand(
                planId: $plan->id->value,
                name: 'Updated Plan',
                description: 'Updated commercial catalogue plan.',
                displayOrder: 11,
                expectedVersion: 1,
                occurredAt: '2026-07-14T06:30:00Z',
                actorPlatformIdentityId: $this->uuid(500),
                correlationId: $this->uuid(501),
            ),
        );
        $updatedBillingOption = (new UpdateBillingOptionService($billingOptionRepository))->execute(
            new UpdateBillingOptionCommand(
                billingOptionId: $billingOption->id->value,
                name: 'Updated billing option',
                availability: CatalogueAvailability::Available->value,
                recurrenceClassification: RecurrenceClassification::Recurring->value,
                intervalUnit: BillingInterval::Year->value,
                intervalCount: 2,
                effectiveStart: '2026-08-01',
                effectiveEnd: '2026-12-31',
                displayOrder: 9,
                expectedVersion: 1,
                occurredAt: '2026-07-14T06:30:00Z',
                actorPlatformIdentityId: $this->uuid(502),
                correlationId: $this->uuid(503),
            ),
        );
        $updatedCapability = (new UpdateCapabilityDefinitionService($capabilityRepository))->execute(
            new UpdateCapabilityDefinitionCommand(
                capabilityId: $capability->id->value,
                name: 'Updated Feature',
                description: 'Updated description.',
                commercialMeaning: 'Updated meaning.',
                expectedVersion: 1,
                occurredAt: '2026-07-14T06:30:00Z',
                actorPlatformIdentityId: $this->uuid(504),
                correlationId: $this->uuid(505),
            ),
        );
        $updatedPlanOffering = (new UpdatePlanOfferingService($planOfferingRepository))->execute(
            new UpdatePlanOfferingCommand(
                planOfferingId: $planOffering->id->value,
                amountMinor: 15000,
                currencyCode: 'MYR',
                effectiveStart: '2026-08-01',
                effectiveEnd: '2026-12-31',
                capabilityConfigurationReference: 'capability-package-v2',
                displayOrder: 12,
                expectedVersion: 1,
                occurredAt: '2026-07-14T06:30:00Z',
                actorPlatformIdentityId: $this->uuid(506),
                correlationId: $this->uuid(507),
            ),
        );

        self::assertSame(1, $planRepository->saveCalls);
        self::assertSame(1, $billingOptionRepository->saveCalls);
        self::assertSame(1, $capabilityRepository->saveCalls);
        self::assertSame(1, $planOfferingRepository->saveCalls);

        self::assertSame(2, $updatedPlan->version());
        self::assertSame('Updated Plan', $updatedPlan->name->value);
        self::assertSame('Updated commercial catalogue plan.', $updatedPlan->description);
        self::assertSame(11, $updatedPlan->displayOrder);
        self::assertSame($plan->createdAt, $updatedPlan->createdAt);
        self::assertEquals($this->utc('2026-07-14T06:30:00Z'), $updatedPlan->lastChangedAt);
        self::assertSame($plan->id->value, $updatedPlan->id->value);
        self::assertSame($plan->code->value, $updatedPlan->code->value);
        self::assertSame($plan->lifecycle->status, $updatedPlan->lifecycle->status);

        self::assertSame(2, $updatedBillingOption->version());
        self::assertSame('Updated billing option', $updatedBillingOption->name->value);
        self::assertSame(CatalogueAvailability::Available, $updatedBillingOption->availability);
        self::assertSame(RecurrenceClassification::Recurring, $updatedBillingOption->recurrence);
        self::assertSame(BillingInterval::Year, $updatedBillingOption->duration?->interval);
        self::assertSame(2, $updatedBillingOption->duration?->intervalCount);
        self::assertSame(9, $updatedBillingOption->displayOrder);
        self::assertSame($billingOption->id->value, $updatedBillingOption->id->value);
        self::assertSame($billingOption->code->value, $updatedBillingOption->code->value);

        self::assertSame(2, $updatedCapability->version());
        self::assertSame('Updated Feature', $updatedCapability->name);
        self::assertSame('Updated description.', $updatedCapability->description);
        self::assertSame('Updated meaning.', $updatedCapability->commercialMeaning);
        self::assertSame($capability->id->value, $updatedCapability->id->value);
        self::assertSame($capability->key->value, $updatedCapability->key->value);
        self::assertSame($capability->status, $updatedCapability->status);

        self::assertSame(2, $updatedPlanOffering->version());
        self::assertSame(15000, $updatedPlanOffering->currentPrice->amountMinor);
        self::assertSame('MYR', $updatedPlanOffering->currentPrice->currencyCode);
        self::assertSame('1', $updatedPlanOffering->configurationVersion);
        self::assertSame('capability-package-v2', $updatedPlanOffering->capabilityConfigurationReference);
        self::assertSame(12, $updatedPlanOffering->displayOrder);
        self::assertSame($planOffering->id->value, $updatedPlanOffering->id->value);
        self::assertSame($planOffering->planId->value, $updatedPlanOffering->planId->value);
        self::assertSame($planOffering->billingOptionId->value, $updatedPlanOffering->billingOptionId->value);
        self::assertSame($planOffering->status, $updatedPlanOffering->status);
    }

    public function test_lifecycle_services_load_check_version_save_and_transition_status(): void
    {
        $planRepository = new RecordingPlanRepository($this->plan(version: 1));
        $capabilityRepository = new RecordingCapabilityDefinitionRepository($this->capability(version: 1));
        $planOfferingRepository = new RecordingPlanOfferingRepository($this->planOffering(version: 1));

        $activatedPlan = (new ActivatePlanService($planRepository))->execute(
            new ActivatePlanCommand(
                planId: $planRepository->planId(),
                expectedVersion: 1,
                occurredAt: '2026-07-14T07:00:00Z',
                actorPlatformIdentityId: $this->uuid(600),
                correlationId: $this->uuid(601),
            ),
        );
        $unavailablePlan = (new MakePlanUnavailableService($planRepository))->execute(
            new MakePlanUnavailableCommand(
                planId: $activatedPlan->id->value,
                expectedVersion: 2,
                occurredAt: '2026-07-14T08:00:00Z',
                actorPlatformIdentityId: $this->uuid(602),
                correlationId: $this->uuid(603),
            ),
        );
        $grandfatheredPlan = (new GrandfatherPlanService($planRepository))->execute(
            new GrandfatherPlanCommand(
                planId: $unavailablePlan->id->value,
                expectedVersion: 3,
                occurredAt: '2026-07-14T09:00:00Z',
                actorPlatformIdentityId: $this->uuid(604),
                correlationId: $this->uuid(605),
            ),
        );
        $retiredPlan = (new RetirePlanService($planRepository))->execute(
            new RetirePlanCommand(
                planId: $grandfatheredPlan->id->value,
                expectedVersion: 4,
                occurredAt: '2026-07-14T10:00:00Z',
                actorPlatformIdentityId: $this->uuid(606),
                correlationId: $this->uuid(607),
            ),
        );

        self::assertSame(PlanStatus::Active, $activatedPlan->lifecycle->status);
        self::assertSame(PlanStatus::Unavailable, $unavailablePlan->lifecycle->status);
        self::assertSame(PlanStatus::Grandfathered, $grandfatheredPlan->lifecycle->status);
        self::assertSame(PlanStatus::Retired, $retiredPlan->lifecycle->status);
        self::assertSame(4, $planRepository->saveCalls);
        self::assertSame(5, $retiredPlan->version());

        $activatedCapability = (new ActivateCapabilityDefinitionService($capabilityRepository))->execute(
            new ActivateCapabilityDefinitionCommand(
                capabilityId: $capabilityRepository->capabilityId(),
                expectedVersion: 1,
                occurredAt: '2026-07-14T07:00:00Z',
                actorPlatformIdentityId: $this->uuid(608),
                correlationId: $this->uuid(609),
            ),
        );
        $deprecatedCapability = (new DeprecateCapabilityDefinitionService($capabilityRepository))->execute(
            new DeprecateCapabilityDefinitionCommand(
                capabilityId: $activatedCapability->id->value,
                expectedVersion: 2,
                occurredAt: '2026-07-14T08:00:00Z',
                actorPlatformIdentityId: $this->uuid(610),
                correlationId: $this->uuid(611),
            ),
        );
        $retiredCapability = (new RetireCapabilityDefinitionService($capabilityRepository))->execute(
            new RetireCapabilityDefinitionCommand(
                capabilityId: $deprecatedCapability->id->value,
                expectedVersion: 3,
                occurredAt: '2026-07-14T09:00:00Z',
                actorPlatformIdentityId: $this->uuid(612),
                correlationId: $this->uuid(613),
            ),
        );

        self::assertSame(CapabilityStatus::Active, $activatedCapability->status);
        self::assertSame(CapabilityStatus::Deprecated, $deprecatedCapability->status);
        self::assertSame(CapabilityStatus::Retired, $retiredCapability->status);
        self::assertSame(3, $capabilityRepository->saveCalls);

        $activatedPlanOffering = (new ActivatePlanOfferingService($planOfferingRepository))->execute(
            new ActivatePlanOfferingCommand(
                planOfferingId: $planOfferingRepository->planOfferingId(),
                expectedVersion: 1,
                occurredAt: '2026-07-14T07:00:00Z',
                actorPlatformIdentityId: $this->uuid(614),
                correlationId: $this->uuid(615),
            ),
        );
        $unavailablePlanOffering = (new MakePlanOfferingUnavailableService($planOfferingRepository))->execute(
            new MakePlanOfferingUnavailableCommand(
                planOfferingId: $activatedPlanOffering->id->value,
                expectedVersion: 2,
                occurredAt: '2026-07-14T08:00:00Z',
                actorPlatformIdentityId: $this->uuid(616),
                correlationId: $this->uuid(617),
            ),
        );
        $grandfatheredPlanOffering = (new GrandfatherPlanOfferingService($planOfferingRepository))->execute(
            new GrandfatherPlanOfferingCommand(
                planOfferingId: $unavailablePlanOffering->id->value,
                expectedVersion: 3,
                occurredAt: '2026-07-14T09:00:00Z',
                actorPlatformIdentityId: $this->uuid(618),
                correlationId: $this->uuid(619),
            ),
        );
        $retiredPlanOffering = (new RetirePlanOfferingService($planOfferingRepository))->execute(
            new RetirePlanOfferingCommand(
                planOfferingId: $grandfatheredPlanOffering->id->value,
                expectedVersion: 4,
                occurredAt: '2026-07-14T10:00:00Z',
                actorPlatformIdentityId: $this->uuid(620),
                correlationId: $this->uuid(621),
            ),
        );

        self::assertSame(PlanOfferingStatus::Active, $activatedPlanOffering->status);
        self::assertSame(PlanOfferingStatus::Unavailable, $unavailablePlanOffering->status);
        self::assertSame(PlanOfferingStatus::Grandfathered, $grandfatheredPlanOffering->status);
        self::assertSame(PlanOfferingStatus::Retired, $retiredPlanOffering->status);
        self::assertSame(4, $planOfferingRepository->saveCalls);
        self::assertSame(5, $retiredPlanOffering->version());
    }

    public function test_version_mismatch_and_missing_resources_fail_closed(): void
    {
        $versionedPlanRepository = new RecordingPlanRepository($this->plan(version: 7));

        try {
            (new UpdatePlanDetailsService($versionedPlanRepository))->execute(
                new UpdatePlanDetailsCommand(
                    planId: $versionedPlanRepository->planId(),
                    name: 'Plan',
                    description: 'Description',
                    displayOrder: 1,
                    expectedVersion: 6,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(700),
                    correlationId: $this->uuid(701),
                ),
            );
            self::fail('Expected a version mismatch exception.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(CommercialCatalogueVersionMismatchException::class, $exception);
            self::assertSame(0, $versionedPlanRepository->saveCalls);
        }

        $missingPlanRepository = new RecordingPlanRepository(null);

        try {
            (new ActivatePlanService($missingPlanRepository))->execute(
                new ActivatePlanCommand(
                    planId: $this->uuid(999),
                    expectedVersion: 1,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(702),
                    correlationId: $this->uuid(703),
                ),
            );
            self::fail('Expected a resource-not-found exception.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(CommercialCatalogueResourceNotFoundException::class, $exception);
            self::assertSame(0, $missingPlanRepository->saveCalls);
        }
    }

    public function test_get_services_preserve_null_when_the_resource_is_absent(): void
    {
        $emptyQuery = new RecordingCommercialCatalogueQuery;

        self::assertNull((new GetPlanService($emptyQuery))->execute($this->uuid(800)));
        self::assertNull((new GetBillingOptionService($emptyQuery))->execute($this->uuid(801)));
        self::assertNull((new GetCapabilityDefinitionService($emptyQuery))->execute($this->uuid(802)));
        self::assertNull((new GetPlanOfferingService($emptyQuery))->execute($this->uuid(803)));
    }

    public function test_update_services_fail_closed_for_every_resource_type_on_not_found_and_version_mismatch(): void
    {
        $cases = [
            'Plan' => fn () => (new UpdatePlanDetailsService(new RecordingPlanRepository($this->plan(version: 3))))->execute(
                new UpdatePlanDetailsCommand(
                    planId: $this->uuid(810),
                    name: 'Plan',
                    description: 'Description',
                    displayOrder: 1,
                    expectedVersion: 3,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(811),
                    correlationId: $this->uuid(812),
                ),
            ),
            'BillingOption' => fn () => (new UpdateBillingOptionService(new RecordingBillingOptionRepository($this->billingOption(version: 3))))->execute(
                new UpdateBillingOptionCommand(
                    billingOptionId: $this->uuid(813),
                    name: 'Billing Option',
                    availability: CatalogueAvailability::Available->value,
                    recurrenceClassification: RecurrenceClassification::Recurring->value,
                    intervalUnit: BillingInterval::Month->value,
                    intervalCount: 1,
                    effectiveStart: '2026-07-01',
                    effectiveEnd: null,
                    displayOrder: 1,
                    expectedVersion: 3,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(814),
                    correlationId: $this->uuid(815),
                ),
            ),
            'CapabilityDefinition' => fn () => (new UpdateCapabilityDefinitionService(new RecordingCapabilityDefinitionRepository($this->capability(version: 3))))->execute(
                new UpdateCapabilityDefinitionCommand(
                    capabilityId: $this->uuid(816),
                    name: 'Feature',
                    description: 'Description',
                    commercialMeaning: 'Meaning',
                    expectedVersion: 3,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(817),
                    correlationId: $this->uuid(818),
                ),
            ),
            'PlanOffering' => fn () => (new UpdatePlanOfferingService(new RecordingPlanOfferingRepository($this->planOffering(version: 3))))->execute(
                new UpdatePlanOfferingCommand(
                    planOfferingId: $this->uuid(819),
                    amountMinor: 10000,
                    currencyCode: 'MYR',
                    effectiveStart: '2026-07-01',
                    effectiveEnd: null,
                    capabilityConfigurationReference: 'capability-package-v1',
                    displayOrder: 1,
                    expectedVersion: 3,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(820),
                    correlationId: $this->uuid(821),
                ),
            ),
        ];

        foreach ($cases as $label => $executeAgainstMissingResource) {
            try {
                $executeAgainstMissingResource();
                self::fail('Expected a not-found exception for: '.$label);
            } catch (Throwable $exception) {
                self::assertInstanceOf(CommercialCatalogueResourceNotFoundException::class, $exception, $label);
            }
        }

        $mismatchCases = [
            'Plan' => function (): array {
                $repository = new RecordingPlanRepository($this->plan(version: 3));

                return [$repository, fn () => (new UpdatePlanDetailsService($repository))->execute(
                    new UpdatePlanDetailsCommand(
                        planId: $repository->planId(),
                        name: 'Plan',
                        description: 'Description',
                        displayOrder: 1,
                        expectedVersion: 2,
                        occurredAt: '2026-07-14T06:30:00Z',
                        actorPlatformIdentityId: $this->uuid(822),
                        correlationId: $this->uuid(823),
                    ),
                )];
            },
            'BillingOption' => function (): array {
                $billingOption = $this->billingOption(version: 3);
                $repository = new RecordingBillingOptionRepository($billingOption);

                return [$repository, fn () => (new UpdateBillingOptionService($repository))->execute(
                    new UpdateBillingOptionCommand(
                        billingOptionId: $billingOption->id->value,
                        name: 'Billing Option',
                        availability: CatalogueAvailability::Available->value,
                        recurrenceClassification: RecurrenceClassification::Recurring->value,
                        intervalUnit: BillingInterval::Month->value,
                        intervalCount: 1,
                        effectiveStart: '2026-07-01',
                        effectiveEnd: null,
                        displayOrder: 1,
                        expectedVersion: 2,
                        occurredAt: '2026-07-14T06:30:00Z',
                        actorPlatformIdentityId: $this->uuid(824),
                        correlationId: $this->uuid(825),
                    ),
                )];
            },
            'CapabilityDefinition' => function (): array {
                $capability = $this->capability(version: 3);
                $repository = new RecordingCapabilityDefinitionRepository($capability);

                return [$repository, fn () => (new UpdateCapabilityDefinitionService($repository))->execute(
                    new UpdateCapabilityDefinitionCommand(
                        capabilityId: $capability->id->value,
                        name: 'Feature',
                        description: 'Description',
                        commercialMeaning: 'Meaning',
                        expectedVersion: 2,
                        occurredAt: '2026-07-14T06:30:00Z',
                        actorPlatformIdentityId: $this->uuid(826),
                        correlationId: $this->uuid(827),
                    ),
                )];
            },
            'PlanOffering' => function (): array {
                $planOffering = $this->planOffering(version: 3);
                $repository = new RecordingPlanOfferingRepository($planOffering);

                return [$repository, fn () => (new UpdatePlanOfferingService($repository))->execute(
                    new UpdatePlanOfferingCommand(
                        planOfferingId: $planOffering->id->value,
                        amountMinor: 10000,
                        currencyCode: 'MYR',
                        effectiveStart: '2026-07-01',
                        effectiveEnd: null,
                        capabilityConfigurationReference: 'capability-package-v1',
                        displayOrder: 1,
                        expectedVersion: 2,
                        occurredAt: '2026-07-14T06:30:00Z',
                        actorPlatformIdentityId: $this->uuid(828),
                        correlationId: $this->uuid(829),
                    ),
                )];
            },
        ];

        foreach ($mismatchCases as $label => $setup) {
            [$repository, $execute] = $setup();

            try {
                $execute();
                self::fail('Expected a version mismatch exception for: '.$label);
            } catch (Throwable $exception) {
                self::assertInstanceOf(CommercialCatalogueVersionMismatchException::class, $exception, $label);
                self::assertSame(0, $repository->saveCalls, $label);
            }
        }
    }

    public function test_lifecycle_services_fail_closed_for_every_family_on_not_found_and_version_mismatch(): void
    {
        $notFoundCases = [
            'Plan' => fn () => (new ActivatePlanService(new RecordingPlanRepository(null)))->execute(
                new ActivatePlanCommand(
                    planId: $this->uuid(830),
                    expectedVersion: 1,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(831),
                    correlationId: $this->uuid(832),
                ),
            ),
            'CapabilityDefinition' => fn () => (new ActivateCapabilityDefinitionService(new RecordingCapabilityDefinitionRepository(null)))->execute(
                new ActivateCapabilityDefinitionCommand(
                    capabilityId: $this->uuid(833),
                    expectedVersion: 1,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(834),
                    correlationId: $this->uuid(835),
                ),
            ),
            'PlanOffering' => fn () => (new ActivatePlanOfferingService(new RecordingPlanOfferingRepository(null)))->execute(
                new ActivatePlanOfferingCommand(
                    planOfferingId: $this->uuid(836),
                    expectedVersion: 1,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(837),
                    correlationId: $this->uuid(838),
                ),
            ),
        ];

        foreach ($notFoundCases as $label => $execute) {
            try {
                $execute();
                self::fail('Expected a not-found exception for: '.$label);
            } catch (Throwable $exception) {
                self::assertInstanceOf(CommercialCatalogueResourceNotFoundException::class, $exception, $label);
            }
        }

        $mismatchCases = [
            'Plan' => function (): array {
                $plan = $this->plan(version: 3);
                $repository = new RecordingPlanRepository($plan);

                return [$repository, fn () => (new ActivatePlanService($repository))->execute(
                    new ActivatePlanCommand(
                        planId: $plan->id->value,
                        expectedVersion: 2,
                        occurredAt: '2026-07-14T06:30:00Z',
                        actorPlatformIdentityId: $this->uuid(839),
                        correlationId: $this->uuid(840),
                    ),
                )];
            },
            'CapabilityDefinition' => function (): array {
                $capability = $this->capability(version: 3);
                $repository = new RecordingCapabilityDefinitionRepository($capability);

                return [$repository, fn () => (new ActivateCapabilityDefinitionService($repository))->execute(
                    new ActivateCapabilityDefinitionCommand(
                        capabilityId: $capability->id->value,
                        expectedVersion: 2,
                        occurredAt: '2026-07-14T06:30:00Z',
                        actorPlatformIdentityId: $this->uuid(841),
                        correlationId: $this->uuid(842),
                    ),
                )];
            },
            'PlanOffering' => function (): array {
                $planOffering = $this->planOffering(version: 3);
                $repository = new RecordingPlanOfferingRepository($planOffering);

                return [$repository, fn () => (new ActivatePlanOfferingService($repository))->execute(
                    new ActivatePlanOfferingCommand(
                        planOfferingId: $planOffering->id->value,
                        expectedVersion: 2,
                        occurredAt: '2026-07-14T06:30:00Z',
                        actorPlatformIdentityId: $this->uuid(843),
                        correlationId: $this->uuid(844),
                    ),
                )];
            },
        ];

        foreach ($mismatchCases as $label => $setup) {
            [$repository, $execute] = $setup();

            try {
                $execute();
                self::fail('Expected a version mismatch exception for: '.$label);
            } catch (Throwable $exception) {
                self::assertInstanceOf(CommercialCatalogueVersionMismatchException::class, $exception, $label);
                self::assertSame(0, $repository->saveCalls, $label);
            }
        }
    }

    public function test_invalid_lifecycle_transition_propagates_the_domain_exception_unchanged_for_every_family(): void
    {
        $planRepository = new RecordingPlanRepository($this->plan(version: 1));

        try {
            (new MakePlanUnavailableService($planRepository))->execute(
                new MakePlanUnavailableCommand(
                    planId: $planRepository->planId(),
                    expectedVersion: 1,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(850),
                    correlationId: $this->uuid(851),
                ),
            );
            self::fail('Expected an invalid Plan lifecycle transition to be rejected.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(InvalidPlanLifecycleTransitionException::class, $exception);
            self::assertSame(0, $planRepository->saveCalls);
        }

        $capabilityRepository = new RecordingCapabilityDefinitionRepository($this->capability(version: 1));

        try {
            (new DeprecateCapabilityDefinitionService($capabilityRepository))->execute(
                new DeprecateCapabilityDefinitionCommand(
                    capabilityId: $capabilityRepository->capabilityId(),
                    expectedVersion: 1,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(852),
                    correlationId: $this->uuid(853),
                ),
            );
            self::fail('Expected an invalid Capability Definition lifecycle transition to be rejected.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(InvalidCommercialCatalogueValueException::class, $exception);
            self::assertSame(0, $capabilityRepository->saveCalls);
        }

        $planOfferingRepository = new RecordingPlanOfferingRepository($this->planOffering(version: 1));

        try {
            (new MakePlanOfferingUnavailableService($planOfferingRepository))->execute(
                new MakePlanOfferingUnavailableCommand(
                    planOfferingId: $planOfferingRepository->planOfferingId(),
                    expectedVersion: 1,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(854),
                    correlationId: $this->uuid(855),
                ),
            );
            self::fail('Expected an invalid Plan Offering lifecycle transition to be rejected.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(InvalidPlanOfferingException::class, $exception);
            self::assertSame(0, $planOfferingRepository->saveCalls);
        }
    }

    public function test_repository_stale_write_exception_propagates_unchanged_and_is_not_swallowed(): void
    {
        $plan = $this->plan(version: 1);
        $repository = new StaleWritePlanRepository($plan);

        try {
            (new UpdatePlanDetailsService($repository))->execute(
                new UpdatePlanDetailsCommand(
                    planId: $plan->id->value,
                    name: 'Updated Plan',
                    description: 'Updated commercial catalogue plan.',
                    displayOrder: 1,
                    expectedVersion: 1,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(860),
                    correlationId: $this->uuid(861),
                ),
            );
            self::fail('Expected the repository stale-write exception to propagate.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(StaleCommercialCatalogueWriteException::class, $exception);
        }

        self::assertSame(1, $repository->saveAttempts);
        self::assertSame(1, $plan->version(), 'The object must not be reported as successfully persisted.');
    }

    public function test_update_plan_offering_preserves_status_and_delegates_commercial_invariants_to_the_domain(): void
    {
        $planOffering = $this->planOffering(version: 1);
        $repository = new RecordingPlanOfferingRepository($planOffering);

        $updated = (new UpdatePlanOfferingService($repository))->execute(
            new UpdatePlanOfferingCommand(
                planOfferingId: $planOffering->id->value,
                amountMinor: 20000,
                currencyCode: 'MYR',
                effectiveStart: '2026-08-01',
                effectiveEnd: '2026-12-31',
                capabilityConfigurationReference: 'capability-package-v3',
                displayOrder: 5,
                expectedVersion: 1,
                occurredAt: '2026-07-14T06:30:00Z',
                actorPlatformIdentityId: $this->uuid(870),
                correlationId: $this->uuid(871),
            ),
        );

        self::assertSame(1, $repository->saveCalls);
        self::assertSame($planOffering->status, $updated->status);

        $invariantViolatingRepository = new RecordingPlanOfferingRepository($this->planOffering(version: 1));

        try {
            (new UpdatePlanOfferingService($invariantViolatingRepository))->execute(
                new UpdatePlanOfferingCommand(
                    planOfferingId: $invariantViolatingRepository->planOfferingId(),
                    amountMinor: 20000,
                    currencyCode: 'USD',
                    effectiveStart: '2026-08-01',
                    effectiveEnd: '2026-12-31',
                    capabilityConfigurationReference: 'capability-package-v3',
                    displayOrder: 5,
                    expectedVersion: 1,
                    occurredAt: '2026-07-14T06:30:00Z',
                    actorPlatformIdentityId: $this->uuid(872),
                    correlationId: $this->uuid(873),
                ),
            );
            self::fail('Expected the Domain-owned MYR invariant to reject a non-MYR currency.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(InvalidPlanOfferingException::class, $exception);
            self::assertSame(0, $invariantViolatingRepository->saveCalls);
        }
    }

    public function test_service_inventory_remains_exactly_the_approved_thirty_use_cases(): void
    {
        $services = $this->phpServiceBasenamesIn(dirname(__DIR__, 6).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue');

        self::assertSame(
            [
                'ActivateCapabilityDefinitionService.php',
                'ActivatePlanOfferingService.php',
                'ActivatePlanService.php',
                'ComputeSubscriptionEntitlementService.php',
                'CreateBillingOptionService.php',
                'CreateCapabilityDefinitionService.php',
                'CreatePlanOfferingService.php',
                'CreatePlanService.php',
                'DeprecateCapabilityDefinitionService.php',
                'GetBillingOptionService.php',
                'GetCapabilityDefinitionService.php',
                'GetPlanOfferingService.php',
                'GetPlanService.php',
                'GrandfatherPlanOfferingService.php',
                'GrandfatherPlanService.php',
                'ListAvailableSubscriptionOfferingsService.php',
                'ListBillingOptionsService.php',
                'ListCapabilityDefinitionsService.php',
                'ListPlanOfferingsService.php',
                'ListPlansService.php',
                'MakePlanOfferingUnavailableService.php',
                'MakePlanUnavailableService.php',
                'ResolveSubscriptionOfferingService.php',
                'RetireCapabilityDefinitionService.php',
                'RetirePlanOfferingService.php',
                'RetirePlanService.php',
                'UpdateBillingOptionService.php',
                'UpdateCapabilityDefinitionService.php',
                'UpdatePlanDetailsService.php',
                'UpdatePlanOfferingService.php',
            ],
            $services,
        );
    }

    private function createPlanCommand(): CreatePlanCommand
    {
        return new CreatePlanCommand(
            code: 'configured_plan',
            name: 'Configured Plan',
            description: 'Managed commercial catalogue plan.',
            displayOrder: 7,
            occurredAt: '2026-07-14T05:30:00Z',
            actorPlatformIdentityId: $this->uuid(100),
            correlationId: $this->uuid(101),
        );
    }

    private function createBillingOptionCommand(): CreateBillingOptionCommand
    {
        return new CreateBillingOptionCommand(
            code: 'configured_option',
            name: 'Configured billing option',
            recurrenceClassification: RecurrenceClassification::Recurring->value,
            intervalUnit: BillingInterval::Month->value,
            intervalCount: 1,
            displayOrder: 3,
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            occurredAt: '2026-07-14T05:30:00Z',
            actorPlatformIdentityId: $this->uuid(200),
            correlationId: $this->uuid(201),
        );
    }

    private function createCapabilityCommand(): CreateCapabilityDefinitionCommand
    {
        return new CreateCapabilityDefinitionCommand(
            capabilityKey: 'feature.alpha',
            name: 'Feature Alpha',
            description: 'A governed commercial capability.',
            commercialMeaning: 'Unlocks the commercial capability.',
            occurredAt: '2026-07-14T05:30:00Z',
            actorPlatformIdentityId: $this->uuid(300),
            correlationId: $this->uuid(301),
        );
    }

    private function createPlanOfferingCommand(string $planId, string $billingOptionId): CreatePlanOfferingCommand
    {
        return new CreatePlanOfferingCommand(
            planId: $planId,
            billingOptionId: $billingOptionId,
            amountMinor: 12500,
            currencyCode: 'MYR',
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            capabilityConfigurationReference: 'capability-package-v1',
            displayOrder: 4,
            occurredAt: '2026-07-14T05:30:00Z',
            actorPlatformIdentityId: $this->uuid(400),
            correlationId: $this->uuid(401),
        );
    }

    private function plan(int $version = 0): Plan
    {
        return new Plan(
            new PlanId($this->uuid(10 + $version)),
            new PlanName('Configured Plan'),
            new PlanCode('configured_plan'),
            'Managed commercial catalogue plan.',
            PlanLifecycle::draft(),
            7,
            $this->utc('2026-07-14T05:30:00Z'),
            $this->utc('2026-07-14T05:30:00Z'),
            $version,
        );
    }

    private function billingOption(int $version = 0): BillingOption
    {
        return new BillingOption(
            new BillingOptionId($this->uuid(20 + $version)),
            new BillingOptionCode('configured_option'),
            new BillingOptionName('Configured billing option'),
            CatalogueAvailability::Available,
            RecurrenceClassification::Recurring,
            new BillingDuration(
                BillingInterval::Month,
                1,
            ),
            new EffectivePeriod('2026-07-01', null),
            3,
            $version,
        );
    }

    private function capability(int $version = 0): CapabilityDefinition
    {
        return new CapabilityDefinition(
            new CapabilityId($this->uuid(30 + $version)),
            new CapabilityKey('feature.alpha'),
            'Feature Alpha',
            'A governed commercial capability.',
            'Unlocks the commercial capability.',
            CapabilityStatus::Draft,
            $version,
        );
    }

    private function planOffering(int $version = 0): PlanOffering
    {
        return new PlanOffering(
            new PlanOfferingId($this->uuid(40 + $version)),
            new PlanId($this->plan($version)->id->value),
            new BillingOptionId($this->billingOption($version)->id->value),
            new Money(12500, 'MYR'),
            new EffectivePeriod('2026-07-01', null),
            PlanOfferingStatus::Draft,
            '1',
            'capability-package-v1',
            4,
            $version,
        );
    }

    private function planData(?string $planId = null): PlanData
    {
        return new PlanData(
            planId: $planId ?? $this->uuid(50),
            code: 'configured_plan',
            name: 'Configured Plan',
            description: 'Managed commercial catalogue plan.',
            status: PlanStatus::Draft->value,
            displayOrder: 7,
            createdAt: '2026-07-14T05:30:00Z',
            lastChangedAt: '2026-07-14T05:30:00Z',
        );
    }

    private function billingOptionData(?string $billingOptionId = null): BillingOptionData
    {
        return new BillingOptionData(
            billingOptionId: $billingOptionId ?? $this->uuid(51),
            code: 'configured_option',
            name: 'Configured billing option',
            availability: CatalogueAvailability::Available->value,
            recurrenceClassification: RecurrenceClassification::Recurring->value,
            intervalUnit: BillingInterval::Month->value,
            intervalCount: 1,
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            displayOrder: 3,
        );
    }

    private function capabilityData(?string $capabilityId = null): CapabilityDefinitionData
    {
        return new CapabilityDefinitionData(
            capabilityId: $capabilityId ?? $this->uuid(52),
            capabilityKey: 'feature.alpha',
            name: 'Feature Alpha',
            description: 'A governed commercial capability.',
            commercialMeaning: 'Unlocks the commercial capability.',
            status: CapabilityStatus::Draft->value,
        );
    }

    private function planOfferingData(?string $planOfferingId = null): PlanOfferingData
    {
        return new PlanOfferingData(
            planOfferingId: $planOfferingId ?? $this->uuid(53),
            planId: $this->uuid(54),
            billingOptionId: $this->uuid(55),
            amountMinor: 12500,
            currencyCode: 'MYR',
            status: PlanOfferingStatus::Active->value,
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            configurationVersion: '1',
            capabilityConfigurationReference: 'capability-package-v1',
            displayOrder: 4,
        );
    }

    private function resolvedOfferingData(): ResolvedSubscriptionOfferingData
    {
        return new ResolvedSubscriptionOfferingData(
            planOfferingId: $this->uuid(56),
            planId: $this->uuid(57),
            billingOptionId: $this->uuid(58),
            amountMinor: 12500,
            currencyCode: 'MYR',
            billingPeriodStart: '2026-07-14',
            billingPeriodEnd: '2026-08-13',
            offeringConfigurationVersion: '1',
            capabilityConfigurationReference: 'capability-package-v1',
        );
    }

    private function computedEntitlementData(): ComputedSubscriptionEntitlementData
    {
        return new ComputedSubscriptionEntitlementData(
            planId: $this->uuid(57),
            billingOptionId: $this->uuid(58),
            configurationVersion: '1',
            capabilityKeys: ['feature.alpha', 'feature.beta'],
        );
    }

    private function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }

    /**
     * @return list<string>
     */
    private function phpServiceBasenamesIn(string $directory): array
    {
        $files = [];
        foreach (glob($directory.'/*Service.php') ?: [] as $file) {
            $files[] = basename($file);
        }
        sort($files);

        return $files;
    }
}

final class RecordingIdentifierGenerator implements CommercialCatalogueIdentifierGeneratorInterface
{
    /**
     * @param  list<string>  $identifiers
     */
    public function __construct(private array $identifiers) {}

    public int $calls = 0;

    public function generate(): string
    {
        $this->calls++;

        return array_shift($this->identifiers) ?? sprintf('00000000-0000-4000-8000-%012d', $this->calls);
    }
}

final class RecordingPlanRepository implements PlanRepositoryInterface
{
    public int $findByIdCalls = 0;

    public int $saveCalls = 0;

    public function __construct(private ?Plan $plan = null) {}

    public function findById(PlanId $planId): ?Plan
    {
        $this->findByIdCalls++;

        return $this->plan !== null && $this->plan->id->value === $planId->value ? $this->plan : null;
    }

    public function findByCode(PlanCode $planCode): ?Plan
    {
        return $this->plan !== null && $this->plan->code->value === $planCode->value ? $this->plan : null;
    }

    public function save(Plan $plan): void
    {
        $this->saveCalls++;
        $plan->synchronizeVersion($plan->version() + 1);
        $this->plan = $plan;
    }

    public function existsByCode(PlanCode $planCode): bool
    {
        return $this->findByCode($planCode) !== null;
    }

    public function planId(): string
    {
        return $this->plan?->id->value ?? '00000000-0000-4000-8000-000000000001';
    }
}

final class RecordingBillingOptionRepository implements BillingOptionRepositoryInterface
{
    public int $findByIdCalls = 0;

    public int $saveCalls = 0;

    public function __construct(private ?BillingOption $billingOption = null) {}

    public function findById(BillingOptionId $billingOptionId): ?BillingOption
    {
        $this->findByIdCalls++;

        return $this->billingOption !== null && $this->billingOption->id->value === $billingOptionId->value ? $this->billingOption : null;
    }

    public function findByCode(BillingOptionCode $billingOptionCode): ?BillingOption
    {
        return $this->billingOption !== null && $this->billingOption->code->value === $billingOptionCode->value ? $this->billingOption : null;
    }

    public function save(BillingOption $billingOption): void
    {
        $this->saveCalls++;
        $billingOption->synchronizeVersion($billingOption->version() + 1);
        $this->billingOption = $billingOption;
    }

    public function existsByCode(BillingOptionCode $billingOptionCode): bool
    {
        return $this->findByCode($billingOptionCode) !== null;
    }
}

final class RecordingCapabilityDefinitionRepository implements CapabilityDefinitionRepositoryInterface
{
    public int $findByIdCalls = 0;

    public int $saveCalls = 0;

    public function __construct(private ?CapabilityDefinition $capabilityDefinition = null) {}

    public function findById(CapabilityId $capabilityId): ?CapabilityDefinition
    {
        $this->findByIdCalls++;

        return $this->capabilityDefinition !== null && $this->capabilityDefinition->id->value === $capabilityId->value
            ? $this->capabilityDefinition
            : null;
    }

    public function findByKey(CapabilityKey $capabilityKey): ?CapabilityDefinition
    {
        return $this->capabilityDefinition !== null && $this->capabilityDefinition->key->value === $capabilityKey->value
            ? $this->capabilityDefinition
            : null;
    }

    public function save(CapabilityDefinition $capabilityDefinition): void
    {
        $this->saveCalls++;
        $capabilityDefinition->synchronizeVersion($capabilityDefinition->version() + 1);
        $this->capabilityDefinition = $capabilityDefinition;
    }

    public function existsByKey(CapabilityKey $capabilityKey): bool
    {
        return $this->findByKey($capabilityKey) !== null;
    }

    public function capabilityId(): string
    {
        return $this->capabilityDefinition?->id->value ?? '00000000-0000-4000-8000-000000000003';
    }
}

final class RecordingPlanOfferingRepository implements PlanOfferingRepositoryInterface
{
    public int $findByIdCalls = 0;

    public int $saveCalls = 0;

    public function __construct(private ?PlanOffering $planOffering = null) {}

    public function findById(PlanOfferingId $planOfferingId): ?PlanOffering
    {
        $this->findByIdCalls++;

        return $this->planOffering !== null && $this->planOffering->id->value === $planOfferingId->value ? $this->planOffering : null;
    }

    public function save(PlanOffering $planOffering): void
    {
        $this->saveCalls++;
        $planOffering->synchronizeVersion($planOffering->version() + 1);
        $this->planOffering = $planOffering;
    }

    public function findAvailableForDate(string $effectiveDate): Traversable
    {
        return new ArrayIterator($this->planOffering === null ? [] : [$this->planOffering]);
    }

    public function findByPlan(PlanId $planId): Traversable
    {
        return new ArrayIterator($this->planOffering === null ? [] : [$this->planOffering]);
    }

    public function planOfferingId(): string
    {
        return $this->planOffering?->id->value ?? '00000000-0000-4000-8000-000000000004';
    }
}

final class RecordingPlanCatalogueQuery implements PlanCatalogueQueryInterface
{
    public ?OffsetPaginationInput $receivedPagination = null;

    public function __construct(private PaginatedPlanData $result) {}

    public function listPlans(OffsetPaginationInput $pagination): PaginatedPlanData
    {
        $this->receivedPagination = $pagination;

        return $this->result;
    }
}

final class RecordingBillingOptionCatalogueQuery implements BillingOptionCatalogueQueryInterface
{
    public ?OffsetPaginationInput $receivedPagination = null;

    public function __construct(private PaginatedBillingOptionData $result) {}

    public function listBillingOptions(OffsetPaginationInput $pagination): PaginatedBillingOptionData
    {
        $this->receivedPagination = $pagination;

        return $this->result;
    }
}

final class RecordingCapabilityDefinitionCatalogueQuery implements CapabilityDefinitionCatalogueQueryInterface
{
    public ?OffsetPaginationInput $receivedPagination = null;

    public function __construct(private PaginatedCapabilityDefinitionData $result) {}

    public function listCapabilityDefinitions(OffsetPaginationInput $pagination): PaginatedCapabilityDefinitionData
    {
        $this->receivedPagination = $pagination;

        return $this->result;
    }
}

final class RecordingPlanOfferingCatalogueQuery implements PlanOfferingCatalogueQueryInterface
{
    public ?OffsetPaginationInput $receivedPagination = null;

    public function __construct(private PaginatedPlanOfferingData $result) {}

    public function listPlanOfferings(OffsetPaginationInput $pagination): PaginatedPlanOfferingData
    {
        $this->receivedPagination = $pagination;

        return $this->result;
    }
}

final readonly class RecordingCommercialCatalogueQuery implements CommercialCatalogueQueryInterface
{
    public function __construct(
        private ?PlanData $plan = null,
        private ?BillingOptionData $billingOption = null,
        private ?CapabilityDefinitionData $capability = null,
        private ?PlanOfferingData $planOffering = null,
    ) {}

    public function findPlan(string $planId): ?PlanData
    {
        return $this->plan;
    }

    public function findBillingOption(string $billingOptionId): ?BillingOptionData
    {
        return $this->billingOption;
    }

    public function findPlanOffering(string $planOfferingId): ?PlanOfferingData
    {
        return $this->planOffering;
    }

    public function findCapability(string $capabilityId): ?CapabilityDefinitionData
    {
        return $this->capability;
    }
}

final readonly class RecordingAvailablePlanOfferingQuery implements AvailablePlanOfferingQueryInterface
{
    /**
     * @param  list<PlanOfferingData>  $offerings
     */
    public function __construct(private array $offerings) {}

    public function listAvailableOfferings(string $effectiveDate): array
    {
        return $this->offerings;
    }
}

final readonly class RecordingSubscriptionOfferingResolver implements SubscriptionOfferingResolverInterface
{
    public function __construct(private ?ResolvedSubscriptionOfferingData $resolved) {}

    public function resolve(SubscriptionOfferingSelectionData $selection): ?ResolvedSubscriptionOfferingData
    {
        return $this->resolved;
    }
}

final readonly class RecordingSubscriptionEntitlementComputation implements SubscriptionEntitlementComputationInterface
{
    public function __construct(private ComputedSubscriptionEntitlementData $computed) {}

    public function compute(ResolvedSubscriptionOfferingData $resolvedOffering): ComputedSubscriptionEntitlementData
    {
        return $this->computed;
    }
}

/**
 * A repository fake that models the real repository's final concurrency
 * boundary: every save attempt fails with the same stale-write exception the
 * Postgres repository throws on a compare-and-swap loss, without ever
 * advancing the object's version.
 */
final class StaleWritePlanRepository implements PlanRepositoryInterface
{
    public int $saveAttempts = 0;

    public function __construct(private readonly Plan $plan) {}

    public function findById(PlanId $planId): ?Plan
    {
        return $this->plan->id->value === $planId->value ? $this->plan : null;
    }

    public function findByCode(PlanCode $planCode): ?Plan
    {
        return $this->plan->code->value === $planCode->value ? $this->plan : null;
    }

    public function save(Plan $plan): void
    {
        $this->saveAttempts++;

        throw new StaleCommercialCatalogueWriteException('Plan write rejected because its version is stale.');
    }

    public function existsByCode(PlanCode $planCode): bool
    {
        return $this->findByCode($planCode) !== null;
    }
}
