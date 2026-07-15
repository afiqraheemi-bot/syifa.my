<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CommercialCatalogueIdentifierGenerator;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CommercialCatalogueIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ComputeSubscriptionEntitlementService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListAvailableSubscriptionOfferingsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ResolveSubscriptionOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanDetailsService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AvailablePlanOfferingQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\SubscriptionOfferingResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\SubscriptionOfferingSelectionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanDetailsCommand;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingInterval;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CatalogueAvailability;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\RecurrenceClassification;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class CommercialCatalogueApplicationServicesTest extends TestCase
{
    public function test_identifier_generator_produces_canonical_uuidv4_values_and_domain_values_accept_them(): void
    {
        $generator = new CommercialCatalogueIdentifierGenerator;
        $first = $generator->generate();
        $second = $generator->generate();

        self::assertNotSame($first, $second);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $first,
        );
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $second,
        );

        self::assertSame($first, (new PlanId($first))->value);
        self::assertSame($first, (new BillingOptionId($first))->value);
        self::assertSame($first, (new PlanOfferingId($first))->value);
        self::assertSame($first, (new CapabilityId($first))->value);
    }

    public function test_list_resolve_and_compute_services_delegate_exactly_once_to_their_approved_boundaries(): void
    {
        $offerings = [$this->planOfferingData(), $this->planOfferingData(planOfferingId: $this->uuid(90))];
        $resolved = $this->resolvedOfferingData();
        $computed = $this->computedEntitlementData();
        $selection = new SubscriptionOfferingSelectionData($resolved->planOfferingId, '2026-07-14');

        $list = new ListAvailableSubscriptionOfferingsService(
            new RecordingAvailablePlanOfferingQuery($offerings),
        );
        $resolver = new ResolveSubscriptionOfferingService(
            new RecordingSubscriptionOfferingResolver($resolved),
        );
        $computation = new ComputeSubscriptionEntitlementService(
            new RecordingSubscriptionEntitlementComputation($computed),
        );

        self::assertSame($offerings, $list->execute('2026-07-14'));
        self::assertSame($resolved, $resolver->execute($selection));
        self::assertSame($computed, $computation->execute($resolved));
    }

    public function test_resolve_service_preserves_null(): void
    {
        $resolver = new ResolveSubscriptionOfferingService(
            new RecordingSubscriptionOfferingResolver(null),
        );

        self::assertNull($resolver->execute(new SubscriptionOfferingSelectionData($this->uuid(30), '2026-07-14')));
    }

    public function test_create_plan_service_uses_the_injected_identifier_collaborator_once(): void
    {
        $generator = new RecordingIdentifierGenerator([$this->uuid(1)]);
        $service = new CreatePlanService($generator);
        $plan = $service->execute($this->createPlanCommand());

        self::assertSame(1, $generator->calls);
        self::assertSame($this->uuid(1), $plan->id->value);
        self::assertSame('configured_plan', $plan->code->value);
        self::assertSame('Configured Plan', $plan->name->value);
        self::assertSame('Managed commercial catalogue plan.', $plan->description);
        self::assertSame(PlanStatus::Draft, $plan->lifecycle->status);
        self::assertSame(7, $plan->displayOrder);
        self::assertEquals($this->utc('2026-07-14T05:30:00Z'), $plan->createdAt);
        self::assertEquals($this->utc('2026-07-14T05:30:00Z'), $plan->lastChangedAt);
    }

    public function test_update_plan_details_service_preserves_identifier_lifecycle_and_created_at(): void
    {
        $generator = new RecordingIdentifierGenerator([$this->uuid(1)]);
        $created = (new CreatePlanService($generator))->execute($this->createPlanCommand());
        $updated = (new UpdatePlanDetailsService)->execute(
            $created,
            new UpdatePlanDetailsCommand(
                planId: $created->id->value,
                name: 'Updated Plan',
                description: 'Updated commercial catalogue plan.',
                displayOrder: 11,
                occurredAt: '2026-07-14T06:30:00Z',
                actorPlatformIdentityId: $this->uuid(500),
                correlationId: $this->uuid(501),
            ),
        );

        self::assertSame($created->id->value, $updated->id->value);
        self::assertSame($created->code->value, $updated->code->value);
        self::assertSame($created->lifecycle->status, $updated->lifecycle->status);
        self::assertSame($created->createdAt, $updated->createdAt);
        self::assertSame('Updated Plan', $updated->name->value);
        self::assertSame('Updated commercial catalogue plan.', $updated->description);
        self::assertSame(11, $updated->displayOrder);
        self::assertEquals($this->utc('2026-07-14T06:30:00Z'), $updated->lastChangedAt);
    }

    public function test_create_billing_option_service_uses_the_injected_identifier_collaborator_once_and_rejects_invalid_duration_combinations(): void
    {
        $generator = new RecordingIdentifierGenerator([$this->uuid(2), $this->uuid(3)]);
        $service = new CreateBillingOptionService($generator);

        $recurring = $service->execute(new CreateBillingOptionCommand(
            code: 'configured_option',
            name: 'Configured billing option',
            recurrenceClassification: RecurrenceClassification::Recurring->value,
            intervalUnit: BillingInterval::Month->value,
            intervalCount: 1,
            displayOrder: 3,
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            occurredAt: '2026-07-14T05:30:00Z',
            actorPlatformIdentityId: $this->uuid(600),
            correlationId: $this->uuid(601),
        ));

        self::assertSame(1, $generator->calls);
        self::assertSame($this->uuid(2), $recurring->id->value);
        self::assertSame(CatalogueAvailability::Available, $recurring->availability);
        self::assertSame(RecurrenceClassification::Recurring, $recurring->recurrence);
        self::assertNotNull($recurring->duration);
        self::assertSame(BillingInterval::Month, $recurring->duration->interval);
        self::assertSame(1, $recurring->duration->intervalCount);

        $lifetime = $service->execute(new CreateBillingOptionCommand(
            code: 'lifetime_option',
            name: 'Lifetime billing option',
            recurrenceClassification: RecurrenceClassification::NonRecurring->value,
            intervalUnit: null,
            intervalCount: null,
            displayOrder: 5,
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            occurredAt: '2026-07-14T05:30:00Z',
            actorPlatformIdentityId: $this->uuid(602),
            correlationId: $this->uuid(603),
        ));

        self::assertSame(2, $generator->calls);
        self::assertSame($this->uuid(3), $lifetime->id->value);
        self::assertSame(CatalogueAvailability::Unavailable, $lifetime->availability);
        self::assertSame(RecurrenceClassification::NonRecurring, $lifetime->recurrence);
        self::assertNull($lifetime->duration);
        self::assertFalse($lifetime->isAvailableOn('2026-07-14'));

        $this->expectException(InvalidCommercialCatalogueValueException::class);
        $service->execute(new CreateBillingOptionCommand(
            code: 'broken_option',
            name: 'Broken billing option',
            recurrenceClassification: RecurrenceClassification::Recurring->value,
            intervalUnit: null,
            intervalCount: 1,
            displayOrder: 6,
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            occurredAt: '2026-07-14T05:30:00Z',
            actorPlatformIdentityId: $this->uuid(604),
            correlationId: $this->uuid(605),
        ));
    }

    public function test_create_plan_offering_and_capability_definition_use_the_injected_identifier_collaborator_once(): void
    {
        $generator = new RecordingIdentifierGenerator([$this->uuid(4), $this->uuid(5)]);
        $planOffering = (new CreatePlanOfferingService($generator))->execute(new CreatePlanOfferingCommand(
            planId: $this->uuid(1),
            billingOptionId: $this->uuid(2),
            amountMinor: 12500,
            currencyCode: 'MYR',
            effectiveStart: '2026-07-01',
            effectiveEnd: null,
            capabilityConfigurationReference: 'capability-package-v1',
            displayOrder: 4,
            occurredAt: '2026-07-14T05:30:00Z',
            actorPlatformIdentityId: $this->uuid(604),
            correlationId: $this->uuid(605),
        ));
        $capability = (new CreateCapabilityDefinitionService($generator))->execute(new CreateCapabilityDefinitionCommand(
            capabilityKey: 'feature.alpha',
            name: 'Feature Alpha',
            description: 'A governed commercial capability.',
            commercialMeaning: 'Unlocks the commercial capability.',
            occurredAt: '2026-07-14T05:30:00Z',
            actorPlatformIdentityId: $this->uuid(606),
            correlationId: $this->uuid(607),
        ));

        self::assertSame(2, $generator->calls);
        self::assertSame($this->uuid(4), $planOffering->id->value);
        self::assertSame(PlanOfferingStatus::Draft, $planOffering->status);
        self::assertSame('1', $planOffering->configurationVersion);
        self::assertSame('capability-package-v1', $planOffering->capabilityConfigurationReference);

        self::assertSame($this->uuid(5), $capability->id->value);
        self::assertSame(CapabilityStatus::Draft, $capability->status);
        self::assertSame('feature.alpha', $capability->key->value);
    }

    public function test_final_service_inventory_is_exactly_eight_use_cases(): void
    {
        $this->assertSame(
            [
                'ComputeSubscriptionEntitlementService.php',
                'CreateBillingOptionService.php',
                'CreateCapabilityDefinitionService.php',
                'CreatePlanOfferingService.php',
                'CreatePlanService.php',
                'ListAvailableSubscriptionOfferingsService.php',
                'ResolveSubscriptionOfferingService.php',
                'UpdatePlanDetailsService.php',
            ],
            $this->phpServiceBasenamesIn(dirname(__DIR__, 6).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue'),
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

    private function computedEntitlementData(): ComputedSubscriptionEntitlementData
    {
        return new ComputedSubscriptionEntitlementData(
            planId: $this->uuid(1),
            billingOptionId: $this->uuid(2),
            configurationVersion: '1',
            capabilityKeys: ['feature.alpha', 'feature.beta'],
        );
    }

    private function planOfferingData(?string $planOfferingId = null): PlanOfferingData
    {
        return new PlanOfferingData(
            planOfferingId: $planOfferingId ?? $this->uuid(30),
            planId: $this->uuid(1),
            billingOptionId: $this->uuid(2),
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

    private function resolvedOfferingData(?string $planOfferingId = null): ResolvedSubscriptionOfferingData
    {
        return new ResolvedSubscriptionOfferingData(
            planOfferingId: $planOfferingId ?? $this->uuid(30),
            planId: $this->uuid(1),
            billingOptionId: $this->uuid(2),
            amountMinor: 12500,
            currencyCode: 'MYR',
            billingPeriodStart: '2026-07-14',
            billingPeriodEnd: '2026-08-13',
            offeringConfigurationVersion: '1',
            capabilityConfigurationReference: 'capability-package-v1',
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

    /** @return list<string> */
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

final class RecordingIdentifierGenerator implements CommercialCatalogueIdentifierGeneratorInterface
{
    /** @var list<string> */
    private array $identifiers;

    public int $calls = 0;

    /**
     * @param  list<string>  $identifiers
     */
    public function __construct(array $identifiers)
    {
        $this->identifiers = $identifiers;
    }

    public function generate(): string
    {
        $this->calls++;

        return array_shift($this->identifiers) ?? sprintf('00000000-0000-4000-8000-%012d', $this->calls);
    }
}
