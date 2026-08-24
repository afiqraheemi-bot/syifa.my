<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\WebsiteDesigner;

use App\Support\Dashboard\Application\WebsiteDesigner\Job\WebsiteReviewSubmissionEligibility;
use PHPUnit\Framework\TestCase;

final class WebsiteReviewSubmissionEligibilityTest extends TestCase
{
    public function test_a_draft_website_may_always_be_submitted_for_its_first_review(): void
    {
        self::assertTrue(WebsiteReviewSubmissionEligibility::canSubmitForReview('draft', null, false, 'assigned'));
    }

    public function test_a_reviewed_website_whose_approval_no_longer_matches_the_current_version_may_be_resubmitted(): void
    {
        self::assertTrue(WebsiteReviewSubmissionEligibility::canSubmitForReview('ready_for_review', 'approved', false, 'in_review'));
    }

    public function test_a_pending_approval_blocks_resubmission_regardless_of_lifecycle(): void
    {
        self::assertFalse(WebsiteReviewSubmissionEligibility::canSubmitForReview('draft', 'requested', false, 'assigned'));
        self::assertFalse(WebsiteReviewSubmissionEligibility::canSubmitForReview('ready_for_review', 'resubmitted', false, 'in_review'));
    }

    public function test_a_satisfied_approval_with_the_job_already_at_or_past_review_needs_no_resubmission(): void
    {
        self::assertFalse(WebsiteReviewSubmissionEligibility::canSubmitForReview('ready_for_review', 'approved', true, 'in_review'));
        self::assertFalse(WebsiteReviewSubmissionEligibility::canSubmitForReview('ready_for_review', 'approved', true, 'ready_for_launch'));
    }

    /**
     * Production incident, 2026-08-24: an administrative reopen() of an
     * Onboarding Job closed before its Website was ever published moves the
     * Job status backward (to 'assigned') without invalidating the prior
     * approval, which still matches the current Website version. Submit for
     * review and Publish both became permanently unavailable — the old rule
     * treated a satisfied approval as proof the Job had already reached
     * review, which reopen() breaks.
     */
    public function test_a_satisfied_approval_still_allows_resubmission_when_the_job_has_not_yet_reached_review(): void
    {
        self::assertTrue(WebsiteReviewSubmissionEligibility::canSubmitForReview('ready_for_review', 'approved', true, 'assigned'));
        self::assertTrue(WebsiteReviewSubmissionEligibility::canSubmitForReview('ready_for_review', 'approved', true, 'reopened'));
        self::assertTrue(WebsiteReviewSubmissionEligibility::canSubmitForReview('ready_for_review', 'approved', true, 'in_progress'));
        self::assertTrue(WebsiteReviewSubmissionEligibility::canSubmitForReview('ready_for_review', 'approved', true, 'correction_required'));
    }

    public function test_a_published_website_is_not_offered_resubmission(): void
    {
        self::assertFalse(WebsiteReviewSubmissionEligibility::canSubmitForReview('published', 'approved', true, 'completed'));
    }
}
