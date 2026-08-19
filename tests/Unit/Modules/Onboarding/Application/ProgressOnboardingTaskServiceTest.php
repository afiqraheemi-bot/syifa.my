<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Tasks\ProgressOnboardingTaskService;
use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Modules\Onboarding\Contracts\Tasks\ProgressOnboardingTaskCommand;
use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\OnboardingTask;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskResponsibility;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProgressOnboardingTaskServiceTest extends TestCase
{
    public function test_it_clamps_an_out_of_order_occurred_at_instead_of_throwing(): void
    {
        // Reproduces a real incident: a Website Designer completing a task
        // immediately after being assigned (the realistic fast-succession
        // sequence a real designer follows) could pass an `occurredAt` that
        // landed before a timestamp already recorded on the job — the domain
        // correctly rejects any out-of-order lifecycle timestamp, but this
        // service used to pass the command's raw `occurredAt` straight
        // through instead of clamping it forward first, unlike every sibling
        // service that mutates Onboarding Job lifecycle state.
        $designerId = $this->uuid(4);
        $job = OnboardingJob::create(
            new OnboardingJobId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new WebsiteId($this->uuid(3)),
            new DateTimeImmutable('2026-09-01T00:05:00Z'),
        );
        $job->assignWebsiteDesigner(
            new WebsiteDesignerAssignmentId($this->uuid(5)),
            new PlatformIdentityId($designerId),
            new DateTimeImmutable('2026-09-01T00:06:00Z'),
        );
        $taskId = new OnboardingTaskId($this->uuid(6));
        $job->addTask(new OnboardingTask(
            $taskId,
            new OnboardingJobId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            'website_setup',
            'Website setup',
            OnboardingTaskResponsibility::WebsiteDesigner,
            OnboardingTaskStatus::Ready,
            true,
            true,
            null,
            null,
            null,
            null,
            null,
            new DateTimeImmutable('2026-09-01T00:05:00Z'),
            new DateTimeImmutable('2026-09-01T00:05:00Z'),
        ));
        $job->synchronizePersistenceVersion(1);
        $service = new ProgressOnboardingTaskService(
            new InMemoryTaskOnboardingJobRepository($job),
            new RecordingTaskAudit,
            new ImmediateTaskTransaction,
        );

        $service->execute(new ProgressOnboardingTaskCommand(
            $job->id->value,
            $taskId->value,
            'complete',
            1,
            $designerId,
            'website_designer',
            null,
            'evidence:website_setup',
            null,
            null,
            $this->uuid(7),
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        ));

        self::assertSame(OnboardingTaskStatus::Completed, $job->findTask($taskId)?->status);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class InMemoryTaskOnboardingJobRepository implements OnboardingJobRepositoryInterface
{
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
        $this->job = $onboardingJob;
    }
}

final class RecordingTaskAudit implements OnboardingAuditInterface
{
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
    ): void {}

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
    ): void {}

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

final readonly class ImmediateTaskTransaction implements OnboardingWorkflowTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}
