<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Onboarding\Persistence;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\OnboardingTask;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskResponsibility;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use App\Modules\Onboarding\Infrastructure\Persistence\Mappers\OnboardingJobPersistenceMapper;
use App\Modules\Onboarding\Infrastructure\Persistence\Repositories\PostgresOnboardingJobRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * OnboardingTask::transition() rejects an occurredAt earlier than the
 * task's stored updatedAt. The onboarding_tasks migration originally left
 * task_updated_at (and its sibling timestamp columns) at Laravel's default
 * whole-second precision, so two transitions landing in the same second —
 * routine on a fast machine — could be persisted out of their true
 * sub-second order and intermittently trip that guard (observed as a flaky
 * "cannot move backwards in time" failure in
 * ClinicRegistrationToPublishedWebsiteReleaseChainTest on CI). This test
 * proves the fix migration closes the gap by round-tripping a sub-second
 * timestamp exactly, rather than relying on timing luck to reproduce the
 * race itself.
 */
final class OnboardingTaskTimestampPrecisionMigrationTest extends TestCase
{
    private const string CONNECTION = 'onboarding_task_timestamp_precision_postgres';

    private ?ConnectionInterface $connection = null;

    /** @var list<Migration> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('ONBOARDING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires ONBOARDING_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.');
        }

        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '',
            'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        Schema::connection(self::CONNECTION)->dropIfExists('website_designer_assignments');
        Schema::connection(self::CONNECTION)->dropIfExists('onboarding_website_approvals');
        Schema::connection(self::CONNECTION)->dropIfExists('onboarding_tasks');
        Schema::connection(self::CONNECTION)->dropIfExists('onboarding_jobs');

        foreach ([
            'database/migrations/onboarding/2026_07_13_000001_create_onboarding_job_aggregate_tables.php',
            'database/migrations/onboarding/2026_09_03_000001_create_onboarding_website_approvals.php',
            'database/migrations/onboarding/2026_09_08_000001_create_onboarding_tasks.php',
            'database/migrations/onboarding/2026_10_04_000001_widen_onboarding_task_timestamp_precision.php',
        ] as $path) {
            $migration = require base_path($path);
            self::assertInstanceOf(Migration::class, $migration);
            $migration->up();
            $this->migrations[] = $migration;
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_task_timestamp_columns_carry_microsecond_precision(): void
    {
        $columns = $this->connection()->select(<<<'SQL'
            SELECT column_name, datetime_precision
            FROM information_schema.columns
            WHERE table_name = 'onboarding_tasks'
              AND column_name IN ('task_created_at', 'task_updated_at', 'completed_at', 'due_at')
            SQL);

        self::assertCount(4, $columns);
        foreach ($columns as $column) {
            self::assertSame(6, (int) $column->datetime_precision, $column->column_name.' lost microsecond precision.');
        }
    }

    public function test_a_sub_second_updated_at_survives_a_save_and_reload_round_trip(): void
    {
        $job = OnboardingJob::create(
            new OnboardingJobId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new WebsiteId($this->uuid(3)),
            $this->time('10:00:00.100000'),
        );
        $subSecondUpdatedAt = $this->time('10:00:00.900000');
        $job->addTask(new OnboardingTask(
            new OnboardingTaskId($this->uuid(10)),
            $job->id,
            $job->tenantId,
            'service_setup',
            'Prepare Services',
            OnboardingTaskResponsibility::WebsiteDesigner,
            OnboardingTaskStatus::Ready,
            true,
            true,
            null,
            null,
            null,
            null,
            null,
            $this->time('10:00:00.100000'),
            $subSecondUpdatedAt,
        ));

        $repository = new PostgresOnboardingJobRepository($this->connection(), new OnboardingJobPersistenceMapper);
        $repository->save($job);
        $reloaded = $repository->find($job->tenantId, $job->id);

        self::assertNotNull($reloaded);
        $reloadedTask = $reloaded->tasks()[0];
        // Compare by absolute instant (Unix seconds + microseconds), not a
        // timezone-formatted string — the reloaded value round-trips through
        // Postgres in the session's own timezone, which need not match the
        // literal offset the original DateTimeImmutable was constructed with.
        self::assertSame($subSecondUpdatedAt->format('U.u'), $reloadedTask->updatedAt->format('U.u'));

        // The exact race this migration closes: a transition landing 100ms
        // after the stored sub-second updatedAt, but still within the same
        // whole second, must not be rejected as moving backwards in time.
        $sameSecondLaterTransition = $this->time('10:00:00.950000');
        $transitioned = $reloadedTask->transition(OnboardingTaskStatus::Completed, 'evidence:service_setup', null, null, $sameSecondLaterTransition);
        self::assertSame(OnboardingTaskStatus::Completed, $transitioned->status);
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function time(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T'.$time.'+08:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
