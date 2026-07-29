<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Onboarding\Persistence;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\OnboardingTask;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\StaleOnboardingJobWriteException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskResponsibility;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteApprovalId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteApprovalStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentEndReason;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use App\Modules\Onboarding\Infrastructure\Persistence\Mappers\OnboardingJobPersistenceMapper;
use App\Modules\Onboarding\Infrastructure\Persistence\Repositories\PostgresOnboardingJobRepository;
use App\Modules\Onboarding\Infrastructure\Queries\PostgresWebsiteDesignerDashboardReadAdapter;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

final class PostgresOnboardingJobRepositoryTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    private ?PostgresOnboardingJobRepository $repository = null;

    private ?Migration $migration = null;

    private ?Migration $approvalMigration = null;

    private ?Migration $taskMigration = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('ONBOARDING_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped(
                'Requires ONBOARDING_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.',
            );
        }

        config()->set('database.default', 'onboarding_postgres_integration');
        config()->set('database.connections.onboarding_postgres_integration', [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge('onboarding_postgres_integration');
        $this->connection = DB::connection('onboarding_postgres_integration');
        Schema::dropIfExists('website_designer_assignments');
        Schema::dropIfExists('onboarding_website_approvals');
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboarding_jobs');
        $migration = require base_path(
            'database/migrations/onboarding/2026_07_13_000001_create_onboarding_job_aggregate_tables.php',
        );
        self::assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $this->migration->up();
        $approvalMigration = require base_path(
            'database/migrations/onboarding/2026_09_03_000001_create_onboarding_website_approvals.php',
        );
        self::assertInstanceOf(Migration::class, $approvalMigration);
        $this->approvalMigration = $approvalMigration;
        $this->approvalMigration->up();
        $taskMigration = require base_path(
            'database/migrations/onboarding/2026_09_08_000001_create_onboarding_tasks.php',
        );
        self::assertInstanceOf(Migration::class, $taskMigration);
        $this->taskMigration = $taskMigration;
        $this->taskMigration->up();
        $this->repository = new PostgresOnboardingJobRepository(
            $this->connection,
            new OnboardingJobPersistenceMapper,
        );
    }

    protected function tearDown(): void
    {
        if ($this->migration !== null) {
            $this->taskMigration?->down();
            $this->approvalMigration?->down();
            $this->migration->down();
        }

        DB::purge('onboarding_postgres_integration');
        parent::tearDown();
    }

    public function test_it_persists_and_reloads_a_job(): void
    {
        $job = $this->job();
        $this->repository()->save($job);
        $reloaded = $this->repository()->find($job->tenantId, $job->id);

        self::assertNotNull($reloaded);
        self::assertSame(OnboardingJobStatus::Planned, $reloaded->status());
        self::assertSame(1, $reloaded->version());
    }

    public function test_it_persists_and_reloads_assignment_history(): void
    {
        $job = $this->jobWithAssignment();
        $this->repository()->save($job);
        $reloaded = $this->repository()->find($job->tenantId, $job->id);

        self::assertNotNull($reloaded);
        self::assertCount(1, $reloaded->websiteDesignerAssignmentHistory());
        self::assertNotNull($reloaded->activeWebsiteDesignerAssignment());
    }

    public function test_it_persists_and_reloads_onboarding_tasks_with_dependency_lineage(): void
    {
        $job = $this->job();
        $firstId = new OnboardingTaskId($this->uuid(50));
        $job->addTask(new OnboardingTask(
            $firstId,
            $job->id,
            $job->tenantId,
            'clinic_inputs',
            'Provide clinic information',
            OnboardingTaskResponsibility::ClinicOwner,
            OnboardingTaskStatus::AwaitingClinicOwner,
            true,
            true,
            null,
            $this->time('10:00:00')->modify('+14 days'),
            null,
            null,
            null,
            $this->time('10:00:00'),
            $this->time('10:00:00'),
        ));
        $job->addTask(new OnboardingTask(
            new OnboardingTaskId($this->uuid(51)),
            $job->id,
            $job->tenantId,
            'website_setup',
            'Prepare Website',
            OnboardingTaskResponsibility::WebsiteDesigner,
            OnboardingTaskStatus::NotReady,
            true,
            true,
            $firstId,
            $this->time('10:00:00')->modify('+14 days'),
            null,
            null,
            null,
            $this->time('10:00:00'),
            $this->time('10:00:00'),
        ));

        $this->repository()->save($job);
        $reloaded = $this->repository()->find($job->tenantId, $job->id);

        self::assertNotNull($reloaded);
        self::assertCount(2, $reloaded->tasks());
        self::assertSame($firstId->value, $reloaded->tasks()[1]->dependsOnTaskId?->value);
    }

    public function test_reassignment_survives_reload_with_immutable_history(): void
    {
        $job = $this->jobWithAssignment();
        $this->repository()->save($job);
        $job->reassignWebsiteDesigner(
            $this->assignmentId(10),
            $this->assignmentId(11),
            $this->platformIdentityId(21),
            $this->time('10:02:00'),
        );
        $this->repository()->save($job);
        $reloaded = $this->repository()->find($job->tenantId, $job->id);

        self::assertNotNull($reloaded);
        self::assertCount(2, $reloaded->websiteDesignerAssignmentHistory());
        self::assertSame(
            WebsiteDesignerAssignmentEndReason::Reassigned,
            $reloaded->websiteDesignerAssignmentHistory()[0]->endReason,
        );
        self::assertSame($this->uuid(11), $reloaded->activeWebsiteDesignerAssignment()?->id->value);
    }

    public function test_website_approval_and_correction_cycle_survives_reload(): void
    {
        $job = $this->jobWithAssignment();
        $this->repository()->save($job);
        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(30)),
            $this->platformIdentityId(20),
            2,
            3,
            $this->time('10:02:00'),
        );
        $this->repository()->save($job);
        $job->requestWebsiteCorrection(
            new ClinicOwnerAuthorityId($this->uuid(40)),
            'Correct the clinic contact details.',
            $this->time('10:03:00'),
        );
        $this->repository()->save($job);
        $reloaded = $this->repository()->find($job->tenantId, $job->id);

        self::assertNotNull($reloaded);
        self::assertSame(OnboardingJobStatus::CorrectionRequired, $reloaded->status());
        self::assertSame(
            WebsiteApprovalStatus::CorrectionRequested,
            $reloaded->websiteApproval()?->status,
        );
        self::assertSame(
            'Correct the clinic contact details.',
            $reloaded->websiteApproval()?->correctionNote,
        );
    }

    public function test_revocation_survives_reload(): void
    {
        $job = $this->jobWithAssignment();
        $this->repository()->save($job);
        $job->revokeWebsiteDesignerAssignment($this->assignmentId(10), $this->time('10:02:00'));
        $this->repository()->save($job);
        $reloaded = $this->repository()->find($job->tenantId, $job->id);

        self::assertNotNull($reloaded);
        self::assertNull($reloaded->activeWebsiteDesignerAssignment());
        self::assertSame(WebsiteDesignerAssignmentEndReason::Revoked, $reloaded->websiteDesignerAssignmentHistory()[0]->endReason);
    }

    public function test_database_rejects_duplicate_active_assignment(): void
    {
        $job = $this->jobWithAssignment();
        $this->repository()->save($job);

        $this->expectException(QueryException::class);
        $this->connection()->table('website_designer_assignments')->insert([
            'id' => $this->uuid(11),
            'onboarding_job_id' => $job->id->value,
            'tenant_id' => $job->tenantId->value,
            'platform_identity_id' => $this->uuid(21),
            'assignment_status' => 'active',
            'assigned_at' => $this->time('10:02:00')->format('Y-m-d H:i:s.uP'),
            'ended_at' => null,
            'end_reason' => null,
            'created_at' => $this->time('10:02:00')->format('Y-m-d H:i:s.uP'),
            'updated_at' => $this->time('10:02:00')->format('Y-m-d H:i:s.uP'),
        ]);
    }

    public function test_stale_aggregate_version_write_is_rejected(): void
    {
        $job = $this->jobWithAssignment();
        $this->repository()->save($job);
        $firstCopy = $this->repository()->find($job->tenantId, $job->id);
        $staleCopy = $this->repository()->find($job->tenantId, $job->id);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);
        $firstCopy->start($this->time('10:02:00'));
        $this->repository()->save($firstCopy);
        $staleCopy->start($this->time('10:03:00'));

        $this->expectException(StaleOnboardingJobWriteException::class);
        $this->repository()->save($staleCopy);
    }

    public function test_job_and_assignment_changes_are_atomic(): void
    {
        $connection = $this->connection();
        $connection->unprepared(<<<'SQL'
            CREATE FUNCTION syifa_test_reject_assignment() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'forced assignment failure';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER syifa_test_reject_assignment_trigger
            BEFORE INSERT ON website_designer_assignments
            FOR EACH ROW EXECUTE FUNCTION syifa_test_reject_assignment();
            SQL);
        $job = $this->jobWithAssignment();

        try {
            $this->repository()->save($job);
            self::fail('The forced assignment failure should abort the aggregate transaction.');
        } catch (Throwable) {
            self::assertFalse($connection->table('onboarding_jobs')->where('id', $job->id->value)->exists());
            self::assertSame(0, $job->version());
        } finally {
            $connection->unprepared('DROP TRIGGER IF EXISTS syifa_test_reject_assignment_trigger ON website_designer_assignments');
            $connection->unprepared('DROP FUNCTION IF EXISTS syifa_test_reject_assignment()');
        }
    }

    public function test_designer_dashboard_query_projects_current_work_and_assignment_history(): void
    {
        $designerId = $this->uuid(20);
        foreach ([
            31 => ['awaiting_inputs', 'active', null],
            32 => ['in_progress', 'active', null],
            33 => ['correction_required', 'active', null],
            34 => ['ready_for_launch', 'active', null],
            35 => ['completed', 'ended', 'onboarding_job_completed'],
            36 => ['cancelled', 'ended', 'onboarding_job_cancelled'],
        ] as $suffix => [$status, $assignmentStatus, $endReason]) {
            $this->insertDashboardAssignment(
                $suffix,
                $designerId,
                $status,
                $assignmentStatus,
                $endReason,
            );
        }

        $adapter = new PostgresWebsiteDesignerDashboardReadAdapter($this->connection());
        $data = $adapter->forPlatformIdentity($designerId);

        self::assertSame(4, $data->assignedJobs);
        self::assertSame(1, $data->pendingContentCollection);
        self::assertSame(1, $data->websiteSetup);
        self::assertSame(1, $data->reviewAndRevision);
        self::assertSame(1, $data->readyToPublish);
        self::assertSame(1, $data->completedProjects);
        self::assertCount(5, $data->recentAssignments);
        self::assertSame($this->uuid(36), $data->recentAssignments[0]->assignmentId);

        $filtered = $adapter->queue($designerId, 'in_progress', null, 10, '000000000132');
        self::assertCount(1, $filtered);
        self::assertSame($this->uuid(132), $filtered[0]->onboardingJobId);
        self::assertSame('in_progress', $filtered[0]->status);

        $firstPage = $adapter->queue($designerId, null, null, 3, null);
        self::assertSame([$this->uuid(34), $this->uuid(33), $this->uuid(32)], array_column($firstPage, 'assignmentId'));
        $nextPage = $adapter->queue($designerId, null, $this->uuid(33), 3, null);
        self::assertSame([$this->uuid(32), $this->uuid(31)], array_column($nextPage, 'assignmentId'));

        $detail = $adapter->detail($designerId, $this->uuid(132));
        self::assertNotNull($detail);
        self::assertSame('in_progress', $detail->status);
        self::assertSame($this->uuid(232), $detail->tenantId);
        self::assertNotNull($detail->lifecycle['job_created_at']);
        self::assertNull($adapter->detail($this->uuid(99), $this->uuid(132)));
    }

    private function jobWithAssignment(): OnboardingJob
    {
        $job = $this->job();
        $job->assignWebsiteDesigner(
            $this->assignmentId(10),
            $this->platformIdentityId(20),
            $this->time('10:01:00'),
        );

        return $job;
    }

    private function insertDashboardAssignment(
        int $suffix,
        string $designerId,
        string $status,
        string $assignmentStatus,
        ?string $endReason,
    ): void {
        $timestamp = $this->time(sprintf('10:%02d:00', $suffix));
        $jobId = $this->uuid(100 + $suffix);
        $tenantId = $this->uuid(200 + $suffix);

        $this->connection()->table('onboarding_jobs')->insert([
            'id' => $jobId,
            'tenant_id' => $tenantId,
            'website_id' => $this->uuid(300 + $suffix),
            'status' => $status,
            'version' => 1,
            'job_created_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $this->connection()->table('website_designer_assignments')->insert([
            'id' => $this->uuid($suffix),
            'onboarding_job_id' => $jobId,
            'tenant_id' => $tenantId,
            'platform_identity_id' => $designerId,
            'assignment_status' => $assignmentStatus,
            'assigned_at' => $timestamp,
            'ended_at' => $assignmentStatus === 'ended' ? $timestamp : null,
            'end_reason' => $endReason,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function job(): OnboardingJob
    {
        return OnboardingJob::create(
            new OnboardingJobId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new WebsiteId($this->uuid(3)),
            $this->time('10:00:00'),
        );
    }

    private function assignmentId(int $suffix): WebsiteDesignerAssignmentId
    {
        return new WebsiteDesignerAssignmentId($this->uuid($suffix));
    }

    private function platformIdentityId(int $suffix): PlatformIdentityId
    {
        return new PlatformIdentityId($this->uuid($suffix));
    }

    private function repository(): PostgresOnboardingJobRepository
    {
        self::assertNotNull($this->repository);

        return $this->repository;
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
