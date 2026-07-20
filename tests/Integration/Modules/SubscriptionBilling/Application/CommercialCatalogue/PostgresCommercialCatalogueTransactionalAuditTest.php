<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CommercialCatalogueIdentifierGenerator;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanDetailsService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanDetailsCommand;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanLifecycle;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanName;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class PostgresCommercialCatalogueTransactionalAuditTest extends TestCase
{
    private const string CONNECTION_NAME = 'commercial_catalogue_transactional_audit_test';

    private ?ConnectionInterface $connection = null;

    private ?PostgresPlanRepository $planRepository = null;

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

        $this->planRepository = new PostgresPlanRepository($this->connection(), new CommercialCataloguePersistenceMapper);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_create_plan_rolls_back_when_audit_persistence_fails(): void
    {
        $service = new CreatePlanService(
            new CommercialCatalogueIdentifierGenerator,
            $this->planRepository(),
            new FailingAuditEntryRecorder,
        );

        try {
            $this->connection()->transaction(static fn (): Plan => $service->execute(new CreatePlanCommand(
                code: 'audit_failure_plan',
                name: 'Audit Failure Plan',
                description: 'The audit failure should roll back the create mutation.',
                displayOrder: 1,
                occurredAt: '2026-07-20T03:30:00Z',
                actorPlatformIdentityId: '00000000-0000-4000-8000-000000000101',
                correlationId: '00000000-0000-4000-8000-000000000102',
            )));
            self::fail('Expected the audit recorder failure to abort the transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated audit persistence failure.', $exception->getMessage());
        }

        self::assertSame(0, $this->connection()->table('commercial_catalogue_plans')->count());
        self::assertSame(0, $this->connection()->table('commercial_catalogue_plan_versions')->count());
        self::assertNull($this->planRepository()->findByCode(new PlanCode('audit_failure_plan')));
    }

    public function test_update_plan_rolls_back_when_audit_persistence_fails(): void
    {
        $plan = new Plan(
            new PlanId('00000000-0000-4000-8000-000000001101'),
            new PlanName('Seed Plan'),
            new PlanCode('seed_plan'),
            'Seed commercial catalogue plan.',
            PlanLifecycle::draft(),
            1,
            new DateTimeImmutable('2026-07-20T03:00:00Z'),
            new DateTimeImmutable('2026-07-20T03:00:00Z'),
        );
        $this->planRepository()->save($plan);

        $service = new UpdatePlanDetailsService(
            $this->planRepository(),
            new FailingAuditEntryRecorder,
        );

        try {
            $this->connection()->transaction(static fn (): Plan => $service->execute(new UpdatePlanDetailsCommand(
                planId: $plan->id->value,
                name: 'Seed Plan Updated',
                description: 'Updated description.',
                displayOrder: 2,
                expectedVersion: 1,
                occurredAt: '2026-07-20T04:00:00Z',
                actorPlatformIdentityId: '00000000-0000-4000-8000-000000000103',
                correlationId: '00000000-0000-4000-8000-000000000104',
            )));
            self::fail('Expected the audit recorder failure to abort the transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated audit persistence failure.', $exception->getMessage());
        }

        self::assertSame(1, $this->connection()->table('commercial_catalogue_plans')->count());
        self::assertSame(1, $this->connection()->table('commercial_catalogue_plan_versions')->count());
        self::assertSame('seed_plan', $this->planRepository()->findById($plan->id)?->code->value);
    }

    private function connection(): ConnectionInterface
    {
        return $this->connection ?? throw new \LogicException('Connection not initialised.');
    }

    private function planRepository(): PostgresPlanRepository
    {
        return $this->planRepository ?? throw new \LogicException('Repository not initialised.');
    }
}

final class FailingAuditEntryRecorder implements AuditEntryRecorderInterface
{
    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        throw new RuntimeException('Simulated audit persistence failure.');
    }
}
