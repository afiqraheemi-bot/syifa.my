<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Domain\Aggregates\OnboardingJob;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\OnboardingTask;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\OnboardingJobCompleted;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\OnboardingJobCreated;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\OnboardingJobReopened;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\WebsiteDesignerAssigned;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\WebsiteDesignerAssignmentRevoked;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events\WebsiteDesignerReassigned;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidWebsiteDesignerAssignmentTransitionException;
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
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OnboardingJobTest extends TestCase
{
    public function test_it_creates_a_website_designer_assignment(): void
    {
        $job = $this->job();
        self::assertInstanceOf(OnboardingJobCreated::class, $job->releaseDomainEvents()[0]);

        $assignment = $job->assignWebsiteDesigner(
            $this->assignmentId(10),
            $this->platformIdentityId(20),
            $this->time('10:01:00'),
        );

        self::assertTrue($assignment->isActive());
        self::assertSame($job->id->value, $assignment->onboardingJobId->value);
        self::assertSame($job->tenantId->value, $assignment->tenantId->value);
        self::assertSame(OnboardingJobStatus::Assigned, $job->status());
        self::assertInstanceOf(WebsiteDesignerAssigned::class, $job->releaseDomainEvents()[0]);
    }

    public function test_it_reassigns_and_automatically_ends_the_previous_assignment(): void
    {
        $job = $this->jobWithAssignment();
        $job->releaseDomainEvents();

        $replacement = $job->reassignWebsiteDesigner(
            $this->assignmentId(10),
            $this->assignmentId(11),
            $this->platformIdentityId(21),
            $this->time('10:02:00'),
        );

        $previous = $job->findWebsiteDesignerAssignment($this->assignmentId(10));
        self::assertNotNull($previous);
        self::assertFalse($previous->isActive());
        self::assertSame(WebsiteDesignerAssignmentEndReason::Reassigned, $previous->endReason);
        self::assertSame($replacement, $job->activeWebsiteDesignerAssignment());
        self::assertCount(2, $job->websiteDesignerAssignmentHistory());
        self::assertInstanceOf(WebsiteDesignerReassigned::class, $job->releaseDomainEvents()[0]);
    }

    public function test_it_revokes_the_active_assignment_immediately(): void
    {
        $job = $this->jobWithAssignment();
        $job->releaseDomainEvents();

        $job->revokeWebsiteDesignerAssignment(
            $this->assignmentId(10),
            $this->time('10:02:00'),
        );

        self::assertNull($job->activeWebsiteDesignerAssignment());
        self::assertNull($job->findActiveAssignmentFor($this->platformIdentityId(20)));
        self::assertSame(OnboardingJobStatus::Blocked, $job->status());
        self::assertInstanceOf(WebsiteDesignerAssignmentRevoked::class, $job->releaseDomainEvents()[0]);
    }

    public function test_it_rejects_a_duplicate_active_assignment(): void
    {
        $job = $this->jobWithAssignment();

        $this->expectException(InvalidWebsiteDesignerAssignmentTransitionException::class);
        $job->assignWebsiteDesigner(
            $this->assignmentId(11),
            $this->platformIdentityId(21),
            $this->time('10:02:00'),
        );
    }

    public function test_it_rejects_cross_job_assignment_substitution(): void
    {
        $firstJob = $this->job(1);
        $otherJob = $this->job(2);
        $otherJob->assignWebsiteDesigner(
            $this->assignmentId(12),
            $this->platformIdentityId(22),
            $this->time('10:01:00'),
        );

        $this->expectException(InvalidWebsiteDesignerAssignmentTransitionException::class);
        $firstJob->revokeWebsiteDesignerAssignment(
            $this->assignmentId(12),
            $this->time('10:02:00'),
        );
    }

    public function test_it_looks_up_only_the_active_assignment(): void
    {
        $job = $this->jobWithAssignment();

        self::assertNotNull($job->findActiveAssignmentFor($this->platformIdentityId(20)));
        self::assertNull($job->findActiveAssignmentFor($this->platformIdentityId(21)));

        $job->reassignWebsiteDesigner(
            $this->assignmentId(10),
            $this->assignmentId(11),
            $this->platformIdentityId(21),
            $this->time('10:02:00'),
        );

        self::assertNull($job->findActiveAssignmentFor($this->platformIdentityId(20)));
        self::assertNotNull($job->findActiveAssignmentFor($this->platformIdentityId(21)));
    }

    public function test_it_rejects_repeated_revocation_and_assignment_identifier_reuse(): void
    {
        $job = $this->jobWithAssignment();
        $job->revokeWebsiteDesignerAssignment($this->assignmentId(10), $this->time('10:02:00'));

        try {
            $job->revokeWebsiteDesignerAssignment($this->assignmentId(10), $this->time('10:03:00'));
            self::fail('Repeated revocation should fail.');
        } catch (InvalidWebsiteDesignerAssignmentTransitionException) {
            self::assertNull($job->activeWebsiteDesignerAssignment());
        }

        $this->expectException(InvalidWebsiteDesignerAssignmentTransitionException::class);
        $job->assignWebsiteDesigner(
            $this->assignmentId(10),
            $this->platformIdentityId(21),
            $this->time('10:04:00'),
        );
    }

    public function test_reassignment_rejects_the_same_platform_identity(): void
    {
        $job = $this->jobWithAssignment();

        $this->expectException(InvalidWebsiteDesignerAssignmentTransitionException::class);
        $job->reassignWebsiteDesigner(
            $this->assignmentId(10),
            $this->assignmentId(11),
            $this->platformIdentityId(20),
            $this->time('10:02:00'),
        );
    }

    public function test_it_enforces_the_onboarding_lifecycle(): void
    {
        $job = $this->jobWithAssignment();
        $job->start($this->time('10:01:30'));
        self::assertSame(OnboardingJobStatus::InProgress, $job->status());

        $job->block($this->time('10:02:00'));
        $job->resume($this->time('10:02:30'));
        $job->submitForReview($this->time('10:03:00'));
        $job->requireCorrection($this->time('10:03:30'));
        $job->submitForReview($this->time('10:04:00'));
        $job->markReadyForLaunch($this->time('10:04:30'));
        $job->complete($this->time('10:05:00'));

        self::assertSame(OnboardingJobStatus::Completed, $job->status());
        self::assertNull($job->activeWebsiteDesignerAssignment());
        self::assertInstanceOf(OnboardingJobCompleted::class, $job->releaseDomainEvents()[2]);

        $job->reopen($this->time('10:06:00'));
        self::assertSame(OnboardingJobStatus::Reopened, $job->status());
        self::assertNull($job->activeWebsiteDesignerAssignment());
        self::assertInstanceOf(OnboardingJobReopened::class, $job->releaseDomainEvents()[0]);
    }

    public function test_it_rejects_invalid_lifecycle_transitions(): void
    {
        $job = $this->job();

        $this->expectException(InvalidOnboardingJobLifecycleTransitionException::class);
        $job->start($this->time('10:01:00'));
    }

    public function test_it_cancels_every_outstanding_task_when_the_job_is_cancelled(): void
    {
        $job = $this->jobWithAssignment();
        $inProgressTaskId = new OnboardingTaskId($this->uuid(300));
        $job->addTask(new OnboardingTask(
            $inProgressTaskId,
            $job->id,
            $job->tenantId,
            'service_setup',
            'Configure active clinic services',
            OnboardingTaskResponsibility::WebsiteDesigner,
            OnboardingTaskStatus::InProgress,
            true,
            true,
            null,
            null,
            null,
            null,
            null,
            $this->time('10:00:00'),
            $this->time('10:00:00'),
        ));
        $completedTaskId = new OnboardingTaskId($this->uuid(301));
        $job->addTask(new OnboardingTask(
            $completedTaskId,
            $job->id,
            $job->tenantId,
            'clinic_inputs',
            'Provide clinic information and content',
            OnboardingTaskResponsibility::ClinicOwner,
            OnboardingTaskStatus::Completed,
            true,
            true,
            null,
            null,
            'evidence',
            null,
            null,
            $this->time('10:00:00'),
            $this->time('10:00:00'),
            $this->time('10:00:00'),
        ));

        $job->cancel($this->time('10:02:00'));

        self::assertSame(OnboardingJobStatus::Cancelled, $job->status());
        self::assertSame(OnboardingTaskStatus::Cancelled, $job->findTask($inProgressTaskId)?->status);
        self::assertSame(OnboardingTaskStatus::Completed, $job->findTask($completedTaskId)?->status);
    }

    public function test_website_approval_is_owned_by_the_job_and_only_the_owner_decision_makes_it_launch_ready(): void
    {
        $job = $this->jobWithAssignment();
        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(30)),
            $this->platformIdentityId(20),
            2,
            4,
            $this->time('10:02:00'),
        );

        self::assertSame(OnboardingJobStatus::InReview, $job->status());
        self::assertSame(WebsiteApprovalStatus::Requested, $job->websiteApproval()?->status);
        self::assertFalse($job->hasApprovedWebsiteVersions(2, 4));

        $job->requestWebsiteCorrection(
            new ClinicOwnerAuthorityId($this->uuid(40)),
            'Update the clinic contact details.',
            $this->time('10:03:00'),
        );
        self::assertSame(OnboardingJobStatus::CorrectionRequired, $job->status());

        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(30)),
            $this->platformIdentityId(20),
            3,
            5,
            $this->time('10:04:00'),
        );
        $job->approveWebsite(new ClinicOwnerAuthorityId($this->uuid(40)), $this->time('10:05:00'));

        self::assertSame(OnboardingJobStatus::ReadyForLaunch, $job->status());
        self::assertTrue($job->hasApprovedWebsiteVersions(3, 5));
        self::assertFalse($job->hasApprovedWebsiteVersions(2, 4));
    }

    public function test_updated_website_can_be_resubmitted_after_an_earlier_version_was_approved(): void
    {
        $job = $this->jobWithAssignment();
        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(30)),
            $this->platformIdentityId(20),
            2,
            4,
            $this->time('10:02:00'),
        );
        $job->approveWebsite(new ClinicOwnerAuthorityId($this->uuid(40)), $this->time('10:03:00'));

        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(30)),
            $this->platformIdentityId(20),
            3,
            5,
            $this->time('10:04:00'),
        );

        self::assertSame(OnboardingJobStatus::InReview, $job->status());
        self::assertSame(WebsiteApprovalStatus::Resubmitted, $job->websiteApproval()?->status);
        self::assertFalse($job->hasApprovedWebsiteVersions(3, 5));
    }

    /**
     * Production incident, 2026-08-24: an administrative reopen() of a Job
     * closed before its Website was ever published leaves the prior approval
     * Approved and pointing at the same, untouched Website version. Without
     * this allowance, the Website Designer could never resubmit for review
     * again unless they first made a pointless content edit just to bump the
     * version number.
     */
    public function test_a_reopened_job_can_resubmit_an_approved_website_without_a_version_change(): void
    {
        $job = $this->jobWithAssignment();
        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(30)),
            $this->platformIdentityId(20),
            2,
            4,
            $this->time('10:02:00'),
        );
        $job->approveWebsite(new ClinicOwnerAuthorityId($this->uuid(40)), $this->time('10:03:00'));
        self::assertSame(OnboardingJobStatus::ReadyForLaunch, $job->status());

        $job->complete($this->time('10:04:00'));
        $job->reopen($this->time('10:05:00'));
        $job->assignWebsiteDesigner(
            $this->assignmentId(11),
            $this->platformIdentityId(20),
            $this->time('10:06:00'),
        );
        self::assertSame(OnboardingJobStatus::Assigned, $job->status());
        self::assertSame(WebsiteApprovalStatus::Approved, $job->websiteApproval()?->status);

        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(30)),
            $this->platformIdentityId(20),
            2,
            4,
            $this->time('10:07:00'),
        );

        self::assertSame(OnboardingJobStatus::InReview, $job->status());
        self::assertSame(WebsiteApprovalStatus::Resubmitted, $job->websiteApproval()?->status);
        self::assertFalse($job->hasApprovedWebsiteVersions(2, 4));
    }

    public function test_an_approved_website_with_no_version_change_still_rejects_resubmission_outside_a_reopen(): void
    {
        $job = $this->jobWithAssignment();
        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(30)),
            $this->platformIdentityId(20),
            2,
            4,
            $this->time('10:02:00'),
        );
        $job->approveWebsite(new ClinicOwnerAuthorityId($this->uuid(40)), $this->time('10:03:00'));
        self::assertSame(OnboardingJobStatus::ReadyForLaunch, $job->status());

        $this->expectException(InvalidOnboardingJobLifecycleTransitionException::class);
        $job->requestWebsiteApproval(
            new WebsiteApprovalId($this->uuid(30)),
            $this->platformIdentityId(20),
            2,
            4,
            $this->time('10:04:00'),
        );
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

    private function job(int $suffix = 1): OnboardingJob
    {
        return OnboardingJob::create(
            new OnboardingJobId($this->uuid($suffix)),
            new TenantId($this->uuid(100 + $suffix)),
            new WebsiteId($this->uuid(200 + $suffix)),
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

    private function time(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T'.$time.'+08:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
