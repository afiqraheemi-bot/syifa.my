<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteApprovalId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteApprovalStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use DateTimeImmutable;

final readonly class WebsiteApproval
{
    public function __construct(
        public WebsiteApprovalId $id,
        public OnboardingJobId $onboardingJobId,
        public TenantId $tenantId,
        public WebsiteId $websiteId,
        public WebsiteApprovalStatus $status,
        public int $websiteVersion,
        public int $draftVersion,
        public PlatformIdentityId $requestedBy,
        public DateTimeImmutable $requestedAt,
        public ?ClinicOwnerAuthorityId $decidedBy = null,
        public ?string $correctionNote = null,
        public ?DateTimeImmutable $decidedAt = null,
    ) {
        if ($websiteVersion < 1 || $draftVersion < 1) {
            throw new InvalidOnboardingJobLifecycleTransitionException(
                'Website Approval must reference positive Website and Draft versions.',
            );
        }
        if ($status === WebsiteApprovalStatus::CorrectionRequested
            && ($decidedBy === null || $decidedAt === null || trim((string) $correctionNote) === '')) {
            throw new InvalidOnboardingJobLifecycleTransitionException(
                'A correction request requires an owner, decision time, and reason.',
            );
        }
        if ($status === WebsiteApprovalStatus::Approved
            && ($decidedBy === null || $decidedAt === null || $correctionNote !== null)) {
            throw new InvalidOnboardingJobLifecycleTransitionException(
                'Website approval requires an owner and decision time without a correction reason.',
            );
        }
        if (in_array($status, [WebsiteApprovalStatus::Requested, WebsiteApprovalStatus::Resubmitted], true)
            && ($decidedBy !== null || $decidedAt !== null || $correctionNote !== null)) {
            throw new InvalidOnboardingJobLifecycleTransitionException(
                'A pending Website Approval cannot contain a decision.',
            );
        }
    }

    public static function request(
        WebsiteApprovalId $id,
        OnboardingJobId $jobId,
        TenantId $tenantId,
        WebsiteId $websiteId,
        int $websiteVersion,
        int $draftVersion,
        PlatformIdentityId $requestedBy,
        DateTimeImmutable $at,
    ): self {
        return new self(
            $id,
            $jobId,
            $tenantId,
            $websiteId,
            WebsiteApprovalStatus::Requested,
            $websiteVersion,
            $draftVersion,
            $requestedBy,
            $at,
        );
    }

    public function requestCorrection(
        ClinicOwnerAuthorityId $owner,
        string $reason,
        DateTimeImmutable $at,
    ): self {
        $this->assertPending('request correction');

        return new self(
            $this->id,
            $this->onboardingJobId,
            $this->tenantId,
            $this->websiteId,
            WebsiteApprovalStatus::CorrectionRequested,
            $this->websiteVersion,
            $this->draftVersion,
            $this->requestedBy,
            $this->requestedAt,
            $owner,
            trim($reason),
            $at,
        );
    }

    public function resubmit(
        int $websiteVersion,
        int $draftVersion,
        PlatformIdentityId $requestedBy,
        DateTimeImmutable $at,
        bool $jobPrecedesReview,
    ): self {
        $isUpdatedApprovedVersion = $this->status === WebsiteApprovalStatus::Approved
            && ($this->websiteVersion !== $websiteVersion || $this->draftVersion !== $draftVersion);
        // An Approved approval whose version still matches the Job usually
        // means nothing needs resubmission - but an administrative reopen()
        // can leave the Job itself behind this same untouched approval, and
        // it must still be able to pass through review again.
        $isApprovedButJobFellBehindReview = $this->status === WebsiteApprovalStatus::Approved
            && $jobPrecedesReview;
        if ($this->status !== WebsiteApprovalStatus::CorrectionRequested
            && ! $isUpdatedApprovedVersion
            && ! $isApprovedButJobFellBehindReview) {
            throw new InvalidOnboardingJobLifecycleTransitionException(
                'Only a correction-requested or newly updated approved Website may be resubmitted.',
            );
        }

        return new self(
            $this->id,
            $this->onboardingJobId,
            $this->tenantId,
            $this->websiteId,
            WebsiteApprovalStatus::Resubmitted,
            $websiteVersion,
            $draftVersion,
            $requestedBy,
            $at,
        );
    }

    public function approve(ClinicOwnerAuthorityId $owner, DateTimeImmutable $at): self
    {
        $this->assertPending('approve');

        return new self(
            $this->id,
            $this->onboardingJobId,
            $this->tenantId,
            $this->websiteId,
            WebsiteApprovalStatus::Approved,
            $this->websiteVersion,
            $this->draftVersion,
            $this->requestedBy,
            $this->requestedAt,
            $owner,
            null,
            $at,
        );
    }

    public function matchesPublication(int $websiteVersion, int $draftVersion): bool
    {
        return $this->status === WebsiteApprovalStatus::Approved
            && $this->websiteVersion === $websiteVersion
            && $this->draftVersion === $draftVersion;
    }

    private function assertPending(string $transition): void
    {
        if (! in_array($this->status, [WebsiteApprovalStatus::Requested, WebsiteApprovalStatus::Resubmitted], true)) {
            throw new InvalidOnboardingJobLifecycleTransitionException(
                sprintf('Website Approval cannot %s while it is %s.', $transition, $this->status->value),
            );
        }
    }
}
