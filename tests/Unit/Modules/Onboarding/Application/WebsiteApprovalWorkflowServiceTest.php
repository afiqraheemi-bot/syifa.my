<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\WebsiteApproval\DecideWebsiteApprovalService;
use App\Modules\Onboarding\Application\WebsiteApproval\RequestWebsiteApprovalService;
use App\Modules\Onboarding\Contracts\WebsiteApproval\DecideWebsiteApprovalCommand;
use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\RequestWebsiteApprovalCommand;
use App\Modules\Onboarding\Contracts\WebsiteApproval\WebsiteApprovalAuditInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WebsiteApprovalWorkflowServiceTest extends TestCase
{
    public function test_assigned_designer_requests_and_tenant_owner_approves_current_versions(): void
    {
        $repository = new ApprovalWorkflowJobRepository($this->assignedJob());
        $audit = new ApprovalWorkflowAudit;
        $request = new RequestWebsiteApprovalService(
            $repository,
            $audit,
            new ImmediateOnboardingWorkflowTransaction,
        );
        $requested = $request->execute(new RequestWebsiteApprovalCommand(
            $this->uuid(1),
            $this->uuid(30),
            $this->uuid(20),
            2,
            4,
            1,
            $this->uuid(50),
            $this->time('10:02:00'),
        ));

        self::assertSame(OnboardingJobStatus::InReview, $requested->status());
        self::assertSame(2, $requested->version());
        self::assertSame(1, $audit->requests);

        $approved = (new DecideWebsiteApprovalService(
            $repository,
            $audit,
            new ImmediateOnboardingWorkflowTransaction,
        ))->execute(new DecideWebsiteApprovalCommand(
            $this->uuid(2),
            $this->uuid(1),
            $this->uuid(40),
            'approve',
            null,
            2,
            $this->uuid(51),
            $this->time('10:03:00'),
        ));

        self::assertSame(OnboardingJobStatus::ReadyForLaunch, $approved->status());
        self::assertTrue($approved->hasApprovedWebsiteVersions(2, 4));
        self::assertSame(1, $audit->decisions);
    }

    public function test_foreign_tenant_cannot_decide_an_approval(): void
    {
        $repository = new ApprovalWorkflowJobRepository($this->assignedJob());
        $service = new DecideWebsiteApprovalService(
            $repository,
            new ApprovalWorkflowAudit,
            new ImmediateOnboardingWorkflowTransaction,
        );

        $this->expectException(InvalidOnboardingJobLifecycleTransitionException::class);
        $service->execute(new DecideWebsiteApprovalCommand(
            $this->uuid(99),
            $this->uuid(1),
            $this->uuid(40),
            'approve',
            null,
            1,
            $this->uuid(51),
            $this->time('10:03:00'),
        ));
    }

    private function assignedJob(): OnboardingJob
    {
        $job = OnboardingJob::create(
            new OnboardingJobId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new WebsiteId($this->uuid(3)),
            $this->time('10:00:00'),
        );
        $job->assignWebsiteDesigner(
            new WebsiteDesignerAssignmentId($this->uuid(10)),
            new PlatformIdentityId($this->uuid(20)),
            $this->time('10:01:00'),
        );
        $job->synchronizePersistenceVersion(1);

        return $job;
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }

    private function time(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-28T'.$time.'+08:00');
    }
}

final class ApprovalWorkflowJobRepository implements OnboardingJobRepositoryInterface
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
        $onboardingJob->synchronizePersistenceVersion($onboardingJob->version() + 1);
        $this->job = $onboardingJob;
    }
}

final readonly class ImmediateOnboardingWorkflowTransaction implements OnboardingWorkflowTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}

final class ApprovalWorkflowAudit implements WebsiteApprovalAuditInterface
{
    public int $requests = 0;

    public int $decisions = 0;

    public function recordWebsiteApprovalRequested(
        string $actorId,
        string $tenantId,
        string $jobId,
        string $approvalId,
        int $websiteVersion,
        int $draftVersion,
        int $resultingJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->requests++;
    }

    public function recordWebsiteApprovalDecision(
        string $actorId,
        string $tenantId,
        string $jobId,
        string $approvalId,
        string $decision,
        int $resultingJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->decisions++;
    }
}
