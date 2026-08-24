<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Job;

/**
 * Whether the Website Designer's "Submit for review" action should be
 * offered. Extracted from WebsiteDesignerJobDetailPage so this specific
 * eligibility rule — the exact rule a 2026-08-24 production incident found
 * completely untested — can be exercised directly, without constructing the
 * whole page assembler and its ~10 collaborators.
 *
 * A currently-satisfied approval for the current Website version normally
 * means the Onboarding Job has already advanced to (or past) `in_review` —
 * OnboardingJob::progressTask() auto-fires markReadyForLaunch() the moment
 * tasks and approval are both satisfied while the Job is `in_review`. But an
 * administrative `reopen()` (the only supported recovery from a Job closed
 * before its Website was actually published) can leave the Job status
 * behind an approval that was never invalidated. Without the job-status
 * check below, that combination made "Submit for review" and "Publish" both
 * permanently unavailable — the eligibility rule assumed a stale approval
 * could only mean the Job was already at or past review, which reopen()
 * breaks.
 */
final readonly class WebsiteReviewSubmissionEligibility
{
    private const array PENDING_APPROVAL_STATUSES = ['requested', 'resubmitted'];

    private const array JOB_STATUSES_BEFORE_REVIEW = ['assigned', 'in_progress', 'blocked', 'correction_required', 'reopened'];

    public static function canSubmitForReview(
        string $websiteLifecycle,
        ?string $approvalStatus,
        bool $currentApprovalSatisfied,
        string $jobStatus,
    ): bool {
        if (in_array($approvalStatus, self::PENDING_APPROVAL_STATUSES, true)) {
            return false;
        }

        if ($websiteLifecycle === 'draft') {
            return true;
        }

        if ($websiteLifecycle !== 'ready_for_review') {
            return false;
        }

        if (! $currentApprovalSatisfied) {
            return true;
        }

        // The approval already covers this exact Website version, but the
        // Job itself has not yet passed through review for it — reopen()
        // moved the Job status backward without touching the approval row.
        return in_array($jobStatus, self::JOB_STATUSES_BEFORE_REVIEW, true);
    }
}
