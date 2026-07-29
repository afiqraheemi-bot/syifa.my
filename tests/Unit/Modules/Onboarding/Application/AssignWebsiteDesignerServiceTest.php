<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Administration\AssignWebsiteDesignerService;
use App\Modules\Onboarding\Application\Administration\ManageOnboardingJobLifecycleService;
use App\Modules\Onboarding\Application\Administration\ReassignWebsiteDesignerService;
use App\Modules\Onboarding\Contracts\Administration\AssignWebsiteDesignerCommand;
use App\Modules\Onboarding\Contracts\Administration\ManageOnboardingJobLifecycleCommand;
use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Modules\Onboarding\Contracts\Administration\ReassignWebsiteDesignerCommand;
use App\Modules\Onboarding\Contracts\Administration\WebsiteDesignerEligibilityInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidWebsiteDesignerAssignmentTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteApprovalId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AssignWebsiteDesignerServiceTest extends TestCase
{
    public function test_it_assigns_only_an_eligible_designer_to_the_authoritative_job_version(): void
    {
        $job = OnboardingJob::create(
            new OnboardingJobId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new WebsiteId($this->uuid(3)),
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        );
        $job->synchronizePersistenceVersion(1);
        $repository = new InMemoryAdminOnboardingJobRepository($job);
        $service = new AssignWebsiteDesignerService(
            $repository,
            new FixedWebsiteDesignerEligibility(true),
            new InMemoryOnboardingAuditRecorder,
        );

        $assignmentId = $service->execute(new AssignWebsiteDesignerCommand(
            $job->id->value,
            $this->uuid(4),
            1,
            $this->uuid(5),
            $this->uuid(6),
            new DateTimeImmutable('2026-09-01T00:01:00Z'),
        ));

        self::assertSame($assignmentId, $job->activeWebsiteDesignerAssignment()?->id->value);
        self::assertSame($this->uuid(4), $job->activeWebsiteDesignerAssignment()?->platformIdentityId->value);
        self::assertSame(1, $repository->saveCalls);
    }

    public function test_it_rejects_ineligible_or_stale_assignment_requests(): void
    {
        $job = OnboardingJob::create(
            new OnboardingJobId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new WebsiteId($this->uuid(3)),
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        );
        $job->synchronizePersistenceVersion(1);
        $service = new AssignWebsiteDesignerService(
            new InMemoryAdminOnboardingJobRepository($job),
            new FixedWebsiteDesignerEligibility(false),
            new InMemoryOnboardingAuditRecorder,
        );

        $this->expectException(InvalidWebsiteDesignerAssignmentTransitionException::class);
        $service->execute(new AssignWebsiteDesignerCommand(
            $job->id->value,
            $this->uuid(4),
            1,
            $this->uuid(5),
            $this->uuid(6),
            new DateTimeImmutable('2026-09-01T00:01:00Z'),
        ));
    }

    public function test_it_reassigns_an_active_job_without_leaving_two_active_assignments(): void
    {
        $job = OnboardingJob::create(
            new OnboardingJobId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new WebsiteId($this->uuid(3)),
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        );
        $job->synchronizePersistenceVersion(1);
        $repository = new InMemoryAdminOnboardingJobRepository($job);
        $audit = new InMemoryOnboardingAuditRecorder;
        $assign = new AssignWebsiteDesignerService(
            $repository,
            new FixedWebsiteDesignerEligibility(true),
            $audit,
        );
        $currentAssignmentId = $assign->execute(new AssignWebsiteDesignerCommand(
            $job->id->value,
            $this->uuid(4),
            1,
            $this->uuid(5),
            $this->uuid(6),
            new DateTimeImmutable('2026-09-01T00:01:00Z'),
        ));

        $replacementId = (new ReassignWebsiteDesignerService(
            $repository,
            new FixedWebsiteDesignerEligibility(true),
            $audit,
        ))->execute(new ReassignWebsiteDesignerCommand(
            $job->id->value,
            $currentAssignmentId,
            $this->uuid(7),
            1,
            $this->uuid(5),
            $this->uuid(8),
            new DateTimeImmutable('2026-09-01T00:02:00Z'),
        ));

        self::assertSame($replacementId, $job->activeWebsiteDesignerAssignment()?->id->value);
        self::assertSame($this->uuid(7), $job->activeWebsiteDesignerAssignment()?->platformIdentityId->value);
        self::assertCount(2, $job->websiteDesignerAssignmentHistory());
        self::assertSame(1, $audit->reassignmentCalls);
    }

    public function test_super_admin_completes_only_a_launch_ready_job_with_audited_version_change(): void
    {
        $job = OnboardingJob::create(
            new OnboardingJobId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new WebsiteId($this->uuid(3)),
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        );
        $job->assignWebsiteDesigner(
            new WebsiteDesignerAssignmentId($this->uuid(10)),
            new PlatformIdentityId($this->uuid(4)),
            new DateTimeImmutable('2026-09-01T00:01:00Z'),
        );
        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(11)),
            new PlatformIdentityId($this->uuid(4)),
            2,
            3,
            new DateTimeImmutable('2026-09-01T00:02:00Z'),
        );
        $job->approveWebsite(
            new ClinicOwnerAuthorityId($this->uuid(12)),
            new DateTimeImmutable('2026-09-01T00:03:00Z'),
        );
        $job->synchronizePersistenceVersion(1);
        $audit = new InMemoryOnboardingAuditRecorder;

        $completed = (new ManageOnboardingJobLifecycleService(
            new InMemoryAdminOnboardingJobRepository($job),
            $audit,
            new ImmediateAdminOnboardingTransaction,
        ))->execute(new ManageOnboardingJobLifecycleCommand(
            $job->id->value,
            'complete',
            null,
            1,
            $this->uuid(5),
            $this->uuid(6),
            new DateTimeImmutable('2026-09-01T00:04:00Z'),
        ));

        self::assertSame(OnboardingJobStatus::Completed, $completed->status());
        self::assertNull($completed->activeWebsiteDesignerAssignment());
        self::assertSame(1, $audit->lifecycleCalls);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class InMemoryAdminOnboardingJobRepository implements OnboardingJobRepositoryInterface
{
    public int $saveCalls = 0;

    public function __construct(private OnboardingJob $job) {}

    public function find(TenantId $tenantId, OnboardingJobId $onboardingJobId): ?OnboardingJob
    {
        return $this->job->tenantId->value === $tenantId->value
            && $this->job->id->value === $onboardingJobId->value ? $this->job : null;
    }

    public function findById(OnboardingJobId $onboardingJobId): ?OnboardingJob
    {
        return $this->job->id->value === $onboardingJobId->value ? $this->job : null;
    }

    public function save(OnboardingJob $onboardingJob): void
    {
        $this->saveCalls++;
        $this->job = $onboardingJob;
    }
}

final readonly class FixedWebsiteDesignerEligibility implements WebsiteDesignerEligibilityInterface
{
    public function __construct(private bool $eligible) {}

    public function isEligible(string $platformIdentityId): bool
    {
        return $this->eligible;
    }
}

final class InMemoryOnboardingAuditRecorder implements OnboardingAuditInterface
{
    public int $reassignmentCalls = 0;

    public int $lifecycleCalls = 0;

    public function recordDesignerAssignment(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $assignmentId,
        string $designerId,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {}

    public function recordDesignerReassignment(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $previousAssignmentId,
        string $newAssignmentId,
        string $designerId,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->reassignmentCalls++;
    }

    public function recordJobLifecycleChange(
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $operation,
        ?string $reason,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->lifecycleCalls++;
    }

    public function recordTaskWaiver(
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $taskId,
        string $reason,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {}
}

final readonly class ImmediateAdminOnboardingTransaction implements OnboardingWorkflowTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}
