<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\PlatformAdministration\Application\AuditEntry\RecordAuditEntryService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Mappers\AuditEntryPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\PostgresAuditEntryRepository;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CommercialCatalogueIdentifierGenerator;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\PlanOfferingAuditTrail;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanOfferingService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetirePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanOfferingRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class PostgresPlanOfferingAuditTest extends TestCase
{
    private const string CONNECTION_NAME = 'plan_offering_audit_postgres_test';

    private ?ConnectionInterface $connection = null;

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

        foreach (['audit_entries', 'commercial_catalogue_plan_offering_versions', 'commercial_catalogue_plan_offerings'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach ([
            'database/migrations/subscription_billing/2026_07_15_000001_create_commercial_catalogue_persistence_tables.php',
            'database/migrations/platform_administration/2026_07_20_000001_create_audit_entries_table.php',
        ] as $path) {
            $migration = require base_path($path);
            self::assertInstanceOf(Migration::class, $migration);
            $this->migrations[] = $migration;
            $migration->up();
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_every_offering_mutation_is_persisted_with_safe_authoritative_audit_metadata(): void
    {
        $connection = $this->connection();
        $repository = new PostgresPlanOfferingRepository($connection, new CommercialCataloguePersistenceMapper);
        $recorder = new RecordAuditEntryService(new PostgresAuditEntryRepository(
            $connection,
            new AuditEntryPersistenceMapper,
        ));
        $audit = new PlanOfferingAuditTrail($recorder);
        $actor = '00000000-0000-4000-8000-000000000701';
        $plan = '00000000-0000-4000-8000-000000000702';
        $billingOption = '00000000-0000-4000-8000-000000000703';
        $this->seedLineage($plan, $billingOption);

        $offering = $connection->transaction(fn (): PlanOffering => (new CreatePlanOfferingService(
            new CommercialCatalogueIdentifierGenerator,
            $repository,
            $audit,
        ))->execute(new CreatePlanOfferingCommand(
            $plan, $billingOption, 100000, 'MYR', '2026-08-01', null, 'safe-v1', 1,
            '2026-07-28T08:00:00Z', $actor, '00000000-0000-4000-8000-000000000711',
        )));
        $offering = $connection->transaction(fn (): PlanOffering => (new UpdatePlanOfferingService(
            $repository,
            $audit,
        ))->execute(new UpdatePlanOfferingCommand(
            $offering->id->value, 110000, 'MYR', '2026-08-01', null, 'safe-v2', 2, 1,
            '2026-07-28T08:01:00Z', $actor, '00000000-0000-4000-8000-000000000712',
        )));
        $offering = $connection->transaction(fn (): PlanOffering => (new ActivatePlanOfferingService(
            $repository,
            $audit,
        ))->execute(new ActivatePlanOfferingCommand(
            $offering->id->value, 2, '2026-07-28T08:02:00Z', $actor,
            '00000000-0000-4000-8000-000000000713',
        )));
        $connection->transaction(fn (): PlanOffering => (new RetirePlanOfferingService(
            $repository,
            $audit,
        ))->execute(new RetirePlanOfferingCommand(
            $offering->id->value, 3, '2026-07-28T08:03:00Z', $actor,
            '00000000-0000-4000-8000-000000000714',
        )));

        $entries = $connection->table('audit_entries')->where('target_id', $offering->id->value)
            ->orderBy('occurred_at')->get();
        self::assertSame([
            'commercial_catalogue.plan_offering.create',
            'commercial_catalogue.plan_offering.update',
            'commercial_catalogue.plan_offering.activate',
            'commercial_catalogue.plan_offering.retire',
        ], $entries->pluck('action')->all());
        self::assertSame([$actor, $actor, $actor, $actor], $entries->pluck('actor_identity_id')->all());

        foreach ($entries as $index => $entry) {
            $metadata = json_decode((string) $entry->safe_metadata, true, flags: JSON_THROW_ON_ERROR);
            self::assertStringContainsString("plan_id={$plan}", $metadata['target_label']);
            self::assertStringContainsString("previous_version={$index}", $metadata['target_label']);
            self::assertStringContainsString('resulting_version='.($index + 1), $metadata['target_label']);
            self::assertArrayNotHasKey('amount_minor', $metadata);
            self::assertArrayNotHasKey('capability_configuration_reference', $metadata);
        }
    }

    public function test_offering_and_version_are_rolled_back_when_durable_audit_fails(): void
    {
        $connection = $this->connection();
        $repository = new PostgresPlanOfferingRepository($connection, new CommercialCataloguePersistenceMapper);
        $this->seedLineage(
            '00000000-0000-4000-8000-000000000721',
            '00000000-0000-4000-8000-000000000722',
        );
        $service = new CreatePlanOfferingService(
            new CommercialCatalogueIdentifierGenerator,
            $repository,
            new PlanOfferingAuditTrail(new OfferingFailingAuditEntryRecorder),
        );

        try {
            $connection->transaction(fn (): PlanOffering => $service->execute(new CreatePlanOfferingCommand(
                '00000000-0000-4000-8000-000000000721',
                '00000000-0000-4000-8000-000000000722',
                100000,
                'MYR',
                '2026-08-01',
                null,
                'safe-v1',
                1,
                '2026-07-28T08:10:00Z',
                '00000000-0000-4000-8000-000000000723',
                '00000000-0000-4000-8000-000000000724',
            )));
            self::fail('Expected durable audit failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated offering audit persistence failure.', $exception->getMessage());
        }

        self::assertSame(0, $connection->table('commercial_catalogue_plan_offerings')->count());
        self::assertSame(0, $connection->table('commercial_catalogue_plan_offering_versions')->count());
        self::assertSame(0, $connection->table('audit_entries')->count());
    }

    private function connection(): ConnectionInterface
    {
        return $this->connection ?? throw new \LogicException('Connection not initialised.');
    }

    private function seedLineage(string $planId, string $billingOptionId): void
    {
        $now = '2026-07-28 08:00:00+00';
        $this->connection()->table('commercial_catalogue_plans')->insert([
            'id' => $planId,
            'code' => 'plan_'.substr($planId, -3),
            'name' => 'Audit Plan',
            'description' => 'Audit fixture.',
            'status' => 'active',
            'display_order' => 1,
            'domain_created_at' => $now,
            'domain_last_changed_at' => $now,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->connection()->table('commercial_catalogue_billing_options')->insert([
            'id' => $billingOptionId,
            'code' => 'billing_'.substr($billingOptionId, -3),
            'name' => 'Annual',
            'availability' => 'available',
            'recurrence_classification' => 'recurring',
            'interval_unit' => 'year',
            'interval_count' => 1,
            'effective_start' => '2026-01-01',
            'effective_end' => null,
            'display_order' => 1,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

final class OfferingFailingAuditEntryRecorder implements AuditEntryRecorderInterface
{
    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        throw new RuntimeException('Simulated offering audit persistence failure.');
    }
}
