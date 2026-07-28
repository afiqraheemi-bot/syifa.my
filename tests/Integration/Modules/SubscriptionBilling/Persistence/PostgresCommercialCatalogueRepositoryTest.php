<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Persistence;

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
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StaleCommercialCatalogueWriteException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresCommercialCatalogueQueryAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresBillingOptionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresCapabilityDefinitionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanOfferingRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

final class PostgresCommercialCatalogueRepositoryTest extends TestCase
{
    private const string CONNECTION_NAME = 'commercial_catalogue_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?PostgresPlanRepository $planRepository = null;

    private ?PostgresBillingOptionRepository $billingOptionRepository = null;

    private ?PostgresPlanOfferingRepository $planOfferingRepository = null;

    private ?PostgresCapabilityDefinitionRepository $capabilityDefinitionRepository = null;

    /** @var list<Migration> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.');
        }

        config()->set('database.default', self::CONNECTION_NAME);
        config()->set('database.connections.'.self::CONNECTION_NAME, [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge(self::CONNECTION_NAME);
        $this->connection = DB::connection(self::CONNECTION_NAME);

        foreach ([
            'commercial_catalogue_plan_offering_versions',
            'commercial_catalogue_plan_offerings',
            'commercial_catalogue_capability_versions',
            'commercial_catalogue_capabilities',
            'commercial_catalogue_billing_option_versions',
            'commercial_catalogue_billing_options',
            'commercial_catalogue_plan_versions',
            'commercial_catalogue_plans',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $migration = require base_path('database/migrations/subscription_billing/2026_07_15_000001_create_commercial_catalogue_persistence_tables.php');
        self::assertInstanceOf(Migration::class, $migration);
        $this->migrations[] = $migration;
        $migration->up();

        $mapper = new CommercialCataloguePersistenceMapper;
        $this->planRepository = new PostgresPlanRepository($this->connection, $mapper);
        $this->billingOptionRepository = new PostgresBillingOptionRepository($this->connection, $mapper);
        $this->planOfferingRepository = new PostgresPlanOfferingRepository($this->connection, $mapper);
        $this->capabilityDefinitionRepository = new PostgresCapabilityDefinitionRepository($this->connection, $mapper);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_it_persists_reloads_and_versions_a_plan(): void
    {
        $plan = $this->plan();
        $this->planRepository()->save($plan);
        self::assertSame(1, $plan->version());

        $reloaded = $this->planRepository()->findById($plan->id);
        self::assertNotNull($reloaded);
        self::assertSame($plan->code->value, $reloaded->code->value);
        self::assertSame(1, $reloaded->version());
        self::assertTrue($this->planRepository()->existsByCode($plan->code));
        self::assertFalse($this->planRepository()->existsByCode(new PlanCode('missing_plan')));

        $plan = $plan->activate($this->time('+1 minute'));
        $this->planRepository()->save($plan);
        self::assertSame(2, $plan->version());
        self::assertSame('active', $this->planRepository()->findByCode($plan->code)?->lifecycle->status->value);
    }

    public function test_it_persists_reloads_and_versions_a_billing_option_and_honours_effective_dates(): void
    {
        $billingOption = $this->billingOption();
        $this->billingOptionRepository()->save($billingOption);

        self::assertSame(1, $billingOption->version());
        self::assertTrue($this->billingOptionRepository()->findById($billingOption->id)?->isAvailableOn('2026-07-01'));
        self::assertNull($this->billingOptionRepository()->findByCode(new BillingOptionCode('missing_option')));
        self::assertTrue($this->billingOptionRepository()->existsByCode($billingOption->code));
        self::assertFalse($this->billingOptionRepository()->existsByCode(new BillingOptionCode('missing-option')));
    }

    public function test_it_persists_reloads_and_versions_a_capability_definition(): void
    {
        $capability = $this->capability();
        $this->capabilityDefinitionRepository()->save($capability);

        self::assertSame(1, $capability->version());
        self::assertSame('configured_capability', $this->capabilityDefinitionRepository()->findByKey($capability->key)?->key->value);
        self::assertTrue($this->capabilityDefinitionRepository()->existsByKey($capability->key));
        self::assertFalse($this->capabilityDefinitionRepository()->existsByKey(new CapabilityKey('missing_capability')));

        $capability = $capability->activate()->deprecate();
        $this->capabilityDefinitionRepository()->save($capability);
        self::assertSame(2, $capability->version());
        self::assertSame('deprecated', $this->capabilityDefinitionRepository()->findById($capability->id)?->status->value);
    }

    public function test_it_persists_reloads_and_versions_a_plan_offering_and_supports_effective_date_queries(): void
    {
        $plan = $this->plan();
        $this->planRepository()->save($plan);
        $billingOption = $this->billingOption();
        $this->billingOptionRepository()->save($billingOption);
        $planOffering = $this->planOffering($plan, $billingOption)->activate();
        $this->planOfferingRepository()->save($planOffering);

        self::assertSame(1, $planOffering->version());
        self::assertNotNull($this->planOfferingRepository()->findById($planOffering->id));
        self::assertCount(1, iterator_to_array($this->planOfferingRepository()->findByPlan($plan->id), false));
        self::assertCount(1, iterator_to_array($this->planOfferingRepository()->findAvailableForDate('2026-07-01'), false));

        $planOffering = $planOffering->makeUnavailable();
        $this->planOfferingRepository()->save($planOffering);
        self::assertSame(2, $planOffering->version());
        self::assertCount(2, iterator_to_array($this->planOfferingRepository()->findByPlan($plan->id), false));
        self::assertCount(0, iterator_to_array($this->planOfferingRepository()->findAvailableForDate('2026-07-01'), false));

        self::assertNotNull($this->connection);
        $queries = new PostgresCommercialCatalogueQueryAdapter($this->connection);
        self::assertNotNull($queries->findPlanOffering($planOffering->id->value));
        $history = $queries->forPlanOffering($planOffering->id->value);
        self::assertCount(2, $history);
        self::assertSame([2, 1], array_column($history, 'version'));
        self::assertSame('MYR', $history[0]->currencyCode);
    }

    public function test_stale_plan_write_is_rejected(): void
    {
        $plan = $this->plan();
        $this->planRepository()->save($plan);

        $firstCopy = $this->planRepository()->findById($plan->id);
        $staleCopy = $this->planRepository()->findById($plan->id);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);

        $firstCopy = $firstCopy->activate($this->time('+1 minute'));
        $this->planRepository()->save($firstCopy);
        $staleCopy = $staleCopy->activate($this->time('+2 minutes'));

        $this->expectException(StaleCommercialCatalogueWriteException::class);
        $this->planRepository()->save($staleCopy);
    }

    public function test_failed_plan_save_leaves_the_in_memory_version_unchanged(): void
    {
        $plan = $this->plan();
        $this->planRepository()->save($plan);

        $duplicate = new Plan(
            new PlanId($this->uuid(99)),
            new PlanName('Duplicate commercial plan'),
            $plan->code,
            'A duplicate governed commercial plan.',
            PlanLifecycle::draft(),
            2,
            $this->time('+1 minute'),
            $this->time('+1 minute'),
        );

        try {
            $this->planRepository()->save($duplicate);
            self::fail('Expected the duplicate code save to fail.');
        } catch (Throwable $throwable) {
            self::assertInstanceOf(QueryException::class, $throwable);
            self::assertSame(0, $duplicate->version());
        }
    }

    private function plan(): Plan
    {
        return new Plan(
            new PlanId($this->uuid(1)),
            new PlanName('Syifa Managed Website'),
            new PlanCode('syifa_managed_website'),
            'Managed website subscription for an eligible clinic.',
            PlanLifecycle::draft(),
            1,
            $this->time(),
            $this->time(),
        );
    }

    private function billingOption(): BillingOption
    {
        return new BillingOption(
            new BillingOptionId($this->uuid(2)),
            new BillingOptionCode('monthly'),
            new BillingOptionName('Monthly'),
            CatalogueAvailability::Available,
            RecurrenceClassification::Recurring,
            new BillingDuration(BillingInterval::Month, 1),
            new EffectivePeriod('2026-07-01'),
            1,
        );
    }

    private function planOffering(Plan $plan, BillingOption $billingOption): PlanOffering
    {
        return new PlanOffering(
            new PlanOfferingId($this->uuid(3)),
            $plan->id,
            $billingOption->id,
            new Money(12500, 'MYR'),
            new EffectivePeriod('2026-07-01'),
            PlanOfferingStatus::Draft,
            'catalogue-v1',
            'capability-package-v1',
            1,
        );
    }

    private function capability(): CapabilityDefinition
    {
        return new CapabilityDefinition(
            new CapabilityId($this->uuid(4)),
            new CapabilityKey('configured_capability'),
            'Configured capability',
            'Describes the product feature.',
            'Unlocks one governed commercial feature.',
            CapabilityStatus::Draft,
        );
    }

    private function planRepository(): PostgresPlanRepository
    {
        self::assertNotNull($this->planRepository);

        return $this->planRepository;
    }

    private function billingOptionRepository(): PostgresBillingOptionRepository
    {
        self::assertNotNull($this->billingOptionRepository);

        return $this->billingOptionRepository;
    }

    private function planOfferingRepository(): PostgresPlanOfferingRepository
    {
        self::assertNotNull($this->planOfferingRepository);

        return $this->planOfferingRepository;
    }

    private function capabilityDefinitionRepository(): PostgresCapabilityDefinitionRepository
    {
        self::assertNotNull($this->capabilityDefinitionRepository);

        return $this->capabilityDefinitionRepository;
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-07-15T08:00:00+00:00');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
