<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Administration\AssignWebsiteDesignerService;
use App\Modules\Onboarding\Contracts\Administration\AssignWebsiteDesignerCommand;
use App\Modules\Onboarding\Contracts\Administration\WebsiteDesignerEligibilityInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidWebsiteDesignerAssignmentTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
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

final class InMemoryOnboardingAuditRecorder implements AuditEntryRecorderInterface
{
    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        return AuditEntry::record(
            new AuditEntryId($auditEntry->auditEntryId),
            $auditEntry->occurredAt,
            AuditActorType::from($auditEntry->actor->type),
            $auditEntry->actor->identityId,
            $auditEntry->tenantId,
            $auditEntry->action,
            $auditEntry->target->type,
            $auditEntry->target->id,
            AuditOutcomeType::from($auditEntry->outcome->outcome),
            $auditEntry->outcome->reasonCode,
            $auditEntry->correlationId,
            $auditEntry->safeMetadata,
        );
    }
}
