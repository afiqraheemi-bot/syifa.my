<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\WebsiteDesignerAssignment;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\OnboardingJobCancelled;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\OnboardingJobCompleted;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\OnboardingJobCreated;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\OnboardingJobReopened;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\WebsiteDesignerAssigned;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\WebsiteDesignerAssignmentRevoked;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\WebsiteDesignerReassigned;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidWebsiteDesignerAssignmentTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobLifecycleTimestamps;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentEndReason;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use DateTimeImmutable;

final class OnboardingJob
{
    /** @var array<string, WebsiteDesignerAssignment> */
    private array $websiteDesignerAssignments = [];

    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        public readonly OnboardingJobId $id,
        public readonly TenantId $tenantId,
        public readonly WebsiteId $websiteId,
        private OnboardingJobStatus $status,
        private OnboardingJobLifecycleTimestamps $lifecycleTimestamps,
        private int $version,
    ) {}

    public static function create(
        OnboardingJobId $id,
        TenantId $tenantId,
        WebsiteId $websiteId,
        DateTimeImmutable $occurredAt,
    ): self {
        $job = new self(
            $id,
            $tenantId,
            $websiteId,
            OnboardingJobStatus::Planned,
            new OnboardingJobLifecycleTimestamps($occurredAt),
            0,
        );
        $job->record(new OnboardingJobCreated(
            $id->value,
            $tenantId->value,
            $websiteId->value,
            $occurredAt,
        ));

        return $job;
    }

    /** @param list<WebsiteDesignerAssignment> $websiteDesignerAssignments */
    public static function reconstitute(
        OnboardingJobId $id,
        TenantId $tenantId,
        WebsiteId $websiteId,
        OnboardingJobStatus $status,
        OnboardingJobLifecycleTimestamps $lifecycleTimestamps,
        array $websiteDesignerAssignments,
        int $version,
    ): self {
        if ($version < 1) {
            throw new InvalidOnboardingJobLifecycleTransitionException(
                'A persisted Onboarding Job requires a positive aggregate version.',
            );
        }

        $job = new self($id, $tenantId, $websiteId, $status, $lifecycleTimestamps, $version);

        foreach ($websiteDesignerAssignments as $assignment) {
            if ($assignment->onboardingJobId->value !== $id->value
                || ! $assignment->belongsToTenant($tenantId)) {
                throw new InvalidWebsiteDesignerAssignmentTransitionException(
                    'Website Designer Assignment history cannot cross Job or Tenant boundaries.',
                );
            }

            $job->assertAssignmentIdentifierIsUnused($assignment->id);

            if ($assignment->isActive() && $job->activeWebsiteDesignerAssignment() !== null) {
                throw new InvalidWebsiteDesignerAssignmentTransitionException(
                    'Reconstituted Onboarding Job history may contain only one active assignment.',
                );
            }

            $job->websiteDesignerAssignments[$assignment->id->value] = $assignment;
        }

        $activeAssignment = $job->activeWebsiteDesignerAssignment();

        if (in_array($status, [
            OnboardingJobStatus::Assigned,
            OnboardingJobStatus::InProgress,
            OnboardingJobStatus::InReview,
            OnboardingJobStatus::CorrectionRequired,
            OnboardingJobStatus::ReadyForLaunch,
        ], true) && $activeAssignment === null) {
            throw new InvalidWebsiteDesignerAssignmentTransitionException(
                'The persisted Onboarding Job lifecycle requires an active assignment.',
            );
        }

        if (in_array($status, [
            OnboardingJobStatus::Planned,
            OnboardingJobStatus::AwaitingInputs,
            OnboardingJobStatus::Completed,
            OnboardingJobStatus::Cancelled,
            OnboardingJobStatus::Reopened,
        ], true) && $activeAssignment !== null) {
            throw new InvalidWebsiteDesignerAssignmentTransitionException(
                'The persisted Onboarding Job lifecycle cannot retain an active assignment.',
            );
        }

        return $job;
    }

    public function status(): OnboardingJobStatus
    {
        return $this->status;
    }

    public function lifecycleTimestamps(): OnboardingJobLifecycleTimestamps
    {
        return $this->lifecycleTimestamps;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizePersistenceVersion(int $version): void
    {
        if ($version !== $this->version + 1) {
            throw new InvalidOnboardingJobLifecycleTransitionException(
                'An Onboarding Job persistence version must advance by exactly one.',
            );
        }

        $this->version = $version;
    }

    public function awaitInputs(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIs(OnboardingJobStatus::Planned, 'await inputs');
        $this->transitionTo(OnboardingJobStatus::AwaitingInputs, $occurredAt);
    }

    public function assignWebsiteDesigner(
        WebsiteDesignerAssignmentId $assignmentId,
        PlatformIdentityId $platformIdentityId,
        DateTimeImmutable $occurredAt,
    ): WebsiteDesignerAssignment {
        if ($this->activeWebsiteDesignerAssignment() !== null) {
            throw new InvalidWebsiteDesignerAssignmentTransitionException(
                'An Onboarding Job may have only one active Website Designer Assignment.',
            );
        }

        if (! in_array($this->status, [
            OnboardingJobStatus::Planned,
            OnboardingJobStatus::AwaitingInputs,
            OnboardingJobStatus::Blocked,
            OnboardingJobStatus::Reopened,
        ], true)) {
            throw new InvalidWebsiteDesignerAssignmentTransitionException(
                sprintf('A Website Designer cannot be assigned while the Onboarding Job is %s.', $this->status->value),
            );
        }

        $this->assertAssignmentIdentifierIsUnused($assignmentId);
        $assignment = $this->newAssignment($assignmentId, $platformIdentityId, $occurredAt);
        $this->websiteDesignerAssignments[$assignmentId->value] = $assignment;

        if ($this->status !== OnboardingJobStatus::Blocked) {
            $this->transitionTo(OnboardingJobStatus::Assigned, $occurredAt);
        }

        $this->record(new WebsiteDesignerAssigned(
            $this->id->value,
            $assignmentId->value,
            $platformIdentityId->value,
            $this->tenantId->value,
            $occurredAt,
        ));

        return $assignment;
    }

    public function reassignWebsiteDesigner(
        WebsiteDesignerAssignmentId $currentAssignmentId,
        WebsiteDesignerAssignmentId $newAssignmentId,
        PlatformIdentityId $newPlatformIdentityId,
        DateTimeImmutable $occurredAt,
    ): WebsiteDesignerAssignment {
        $current = $this->requireActiveAssignment($currentAssignmentId);
        $this->assertAssignmentIdentifierIsUnused($newAssignmentId);

        if ($current->platformIdentityId->value === $newPlatformIdentityId->value) {
            throw new InvalidWebsiteDesignerAssignmentTransitionException(
                'A Website Designer Assignment cannot be reassigned to the same Platform Identity.',
            );
        }

        $this->websiteDesignerAssignments[$currentAssignmentId->value] = $this->endedVersionOf(
            $current,
            WebsiteDesignerAssignmentEndReason::Reassigned,
            $occurredAt,
        );
        $replacement = $this->newAssignment(
            $newAssignmentId,
            $newPlatformIdentityId,
            $occurredAt,
        );
        $this->websiteDesignerAssignments[$newAssignmentId->value] = $replacement;
        $this->record(new WebsiteDesignerReassigned(
            $this->id->value,
            $currentAssignmentId->value,
            $newAssignmentId->value,
            $this->tenantId->value,
            $occurredAt,
        ));

        return $replacement;
    }

    public function revokeWebsiteDesignerAssignment(
        WebsiteDesignerAssignmentId $assignmentId,
        DateTimeImmutable $occurredAt,
    ): void {
        $assignment = $this->requireActiveAssignment($assignmentId);
        $this->websiteDesignerAssignments[$assignmentId->value] = $this->endedVersionOf(
            $assignment,
            WebsiteDesignerAssignmentEndReason::Revoked,
            $occurredAt,
        );
        $this->transitionTo(OnboardingJobStatus::Blocked, $occurredAt);
        $this->record(new WebsiteDesignerAssignmentRevoked(
            $this->id->value,
            $assignmentId->value,
            $assignment->platformIdentityId->value,
            $this->tenantId->value,
            $occurredAt,
        ));
    }

    public function activeWebsiteDesignerAssignment(): ?WebsiteDesignerAssignment
    {
        foreach ($this->websiteDesignerAssignments as $assignment) {
            if ($assignment->isActive()) {
                return $assignment;
            }
        }

        return null;
    }

    public function findWebsiteDesignerAssignment(
        WebsiteDesignerAssignmentId $assignmentId,
    ): ?WebsiteDesignerAssignment {
        return $this->websiteDesignerAssignments[$assignmentId->value] ?? null;
    }

    public function findActiveAssignmentFor(
        PlatformIdentityId $platformIdentityId,
    ): ?WebsiteDesignerAssignment {
        $assignment = $this->activeWebsiteDesignerAssignment();

        return $assignment?->isAssignedTo($platformIdentityId) === true ? $assignment : null;
    }

    /** @return list<WebsiteDesignerAssignment> */
    public function websiteDesignerAssignmentHistory(): array
    {
        return array_values($this->websiteDesignerAssignments);
    }

    public function start(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIs(OnboardingJobStatus::Assigned, 'start');
        $this->assertHasActiveAssignment('start');
        $this->transitionTo(OnboardingJobStatus::InProgress, $occurredAt);
    }

    public function block(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIs(OnboardingJobStatus::InProgress, 'block');
        $this->transitionTo(OnboardingJobStatus::Blocked, $occurredAt);
    }

    public function resume(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIs(OnboardingJobStatus::Blocked, 'resume');
        $this->assertHasActiveAssignment('resume');
        $this->transitionTo(OnboardingJobStatus::InProgress, $occurredAt);
    }

    public function submitForReview(DateTimeImmutable $occurredAt): void
    {
        if (! in_array($this->status, [OnboardingJobStatus::InProgress, OnboardingJobStatus::CorrectionRequired], true)) {
            throw $this->invalidLifecycleTransition('submit for review');
        }

        $this->assertHasActiveAssignment('submit for review');
        $this->transitionTo(OnboardingJobStatus::InReview, $occurredAt);
    }

    public function requireCorrection(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIs(OnboardingJobStatus::InReview, 'require correction');
        $this->transitionTo(OnboardingJobStatus::CorrectionRequired, $occurredAt);
    }

    public function markReadyForLaunch(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIs(OnboardingJobStatus::InReview, 'mark ready for launch');
        $this->assertHasActiveAssignment('mark ready for launch');
        $this->transitionTo(OnboardingJobStatus::ReadyForLaunch, $occurredAt);
    }

    public function complete(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIs(OnboardingJobStatus::ReadyForLaunch, 'complete');
        $this->endActiveAssignment(WebsiteDesignerAssignmentEndReason::OnboardingJobCompleted, $occurredAt);
        $this->transitionTo(OnboardingJobStatus::Completed, $occurredAt);
        $this->record(new OnboardingJobCompleted($this->id->value, $this->tenantId->value, $occurredAt));
    }

    public function cancel(DateTimeImmutable $occurredAt): void
    {
        if (in_array($this->status, [OnboardingJobStatus::Completed, OnboardingJobStatus::Cancelled], true)) {
            throw $this->invalidLifecycleTransition('cancel');
        }

        $this->endActiveAssignment(WebsiteDesignerAssignmentEndReason::OnboardingJobCancelled, $occurredAt);
        $this->transitionTo(OnboardingJobStatus::Cancelled, $occurredAt);
        $this->record(new OnboardingJobCancelled($this->id->value, $this->tenantId->value, $occurredAt));
    }

    public function reopen(DateTimeImmutable $occurredAt): void
    {
        if (! in_array($this->status, [OnboardingJobStatus::Completed, OnboardingJobStatus::Cancelled], true)) {
            throw $this->invalidLifecycleTransition('reopen');
        }

        $this->transitionTo(OnboardingJobStatus::Reopened, $occurredAt);
        $this->record(new OnboardingJobReopened($this->id->value, $this->tenantId->value, $occurredAt));
    }

    /** @return list<object> */
    public function releaseDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function newAssignment(
        WebsiteDesignerAssignmentId $assignmentId,
        PlatformIdentityId $platformIdentityId,
        DateTimeImmutable $occurredAt,
    ): WebsiteDesignerAssignment {
        return new WebsiteDesignerAssignment(
            $assignmentId,
            $this->id,
            $platformIdentityId,
            $this->tenantId,
            WebsiteDesignerAssignmentStatus::Active,
            $occurredAt,
        );
    }

    private function requireActiveAssignment(
        WebsiteDesignerAssignmentId $assignmentId,
    ): WebsiteDesignerAssignment {
        $assignment = $this->findWebsiteDesignerAssignment($assignmentId);

        if ($assignment === null || ! $assignment->isActive()) {
            throw new InvalidWebsiteDesignerAssignmentTransitionException(
                'The requested active Website Designer Assignment does not belong to this Onboarding Job.',
            );
        }

        return $assignment;
    }

    private function assertAssignmentIdentifierIsUnused(
        WebsiteDesignerAssignmentId $assignmentId,
    ): void {
        if ($this->findWebsiteDesignerAssignment($assignmentId) !== null) {
            throw new InvalidWebsiteDesignerAssignmentTransitionException(
                'A Website Designer Assignment identifier cannot be reused.',
            );
        }
    }

    private function endedVersionOf(
        WebsiteDesignerAssignment $assignment,
        WebsiteDesignerAssignmentEndReason $reason,
        DateTimeImmutable $occurredAt,
    ): WebsiteDesignerAssignment {
        return new WebsiteDesignerAssignment(
            $assignment->id,
            $this->id,
            $assignment->platformIdentityId,
            $this->tenantId,
            WebsiteDesignerAssignmentStatus::Ended,
            $assignment->assignedAt,
            $occurredAt,
            $reason,
        );
    }

    private function endActiveAssignment(
        WebsiteDesignerAssignmentEndReason $reason,
        DateTimeImmutable $occurredAt,
    ): void {
        $assignment = $this->activeWebsiteDesignerAssignment();

        if ($assignment !== null) {
            $this->websiteDesignerAssignments[$assignment->id->value] = $this->endedVersionOf(
                $assignment,
                $reason,
                $occurredAt,
            );
        }
    }

    private function assertHasActiveAssignment(string $transition): void
    {
        if ($this->activeWebsiteDesignerAssignment() === null) {
            throw new InvalidOnboardingJobLifecycleTransitionException(sprintf(
                'Onboarding Job cannot %s without an active Website Designer Assignment.',
                $transition,
            ));
        }
    }

    private function assertStatusIs(OnboardingJobStatus $expected, string $transition): void
    {
        if ($this->status !== $expected) {
            throw $this->invalidLifecycleTransition($transition);
        }
    }

    private function invalidLifecycleTransition(string $transition): InvalidOnboardingJobLifecycleTransitionException
    {
        return new InvalidOnboardingJobLifecycleTransitionException(sprintf(
            'Onboarding Job in %s state cannot %s.',
            $this->status->value,
            $transition,
        ));
    }

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    private function transitionTo(OnboardingJobStatus $status, DateTimeImmutable $occurredAt): void
    {
        $this->lifecycleTimestamps = $this->lifecycleTimestamps->transitionedTo($status, $occurredAt);
        $this->status = $status;
    }
}
