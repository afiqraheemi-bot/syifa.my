<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidWebsiteDesignerAssignmentStateException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentEndReason;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentStatus;
use DateTimeImmutable;

final readonly class WebsiteDesignerAssignment
{
    public function __construct(
        public WebsiteDesignerAssignmentId $id,
        public OnboardingJobId $onboardingJobId,
        public PlatformIdentityId $platformIdentityId,
        public TenantId $tenantId,
        public WebsiteDesignerAssignmentStatus $status,
        public DateTimeImmutable $assignedAt,
        public ?DateTimeImmutable $endedAt = null,
        public ?WebsiteDesignerAssignmentEndReason $endReason = null,
    ) {
        if ($status === WebsiteDesignerAssignmentStatus::Active
            && ($endedAt !== null || $endReason !== null)) {
            throw new InvalidWebsiteDesignerAssignmentStateException(
                'An active Website Designer Assignment cannot contain ending details.',
            );
        }

        if ($status === WebsiteDesignerAssignmentStatus::Ended
            && ($endedAt === null || $endReason === null)) {
            throw new InvalidWebsiteDesignerAssignmentStateException(
                'An ended Website Designer Assignment requires an ending time and reason.',
            );
        }

        if ($endedAt !== null && $endedAt < $assignedAt) {
            throw new InvalidWebsiteDesignerAssignmentStateException(
                'A Website Designer Assignment cannot end before it was assigned.',
            );
        }
    }

    public function isActive(): bool
    {
        return $this->status === WebsiteDesignerAssignmentStatus::Active;
    }

    public function belongsToTenant(TenantId $tenantId): bool
    {
        return $this->tenantId->value === $tenantId->value;
    }

    public function isAssignedTo(PlatformIdentityId $platformIdentityId): bool
    {
        return $this->platformIdentityId->value === $platformIdentityId->value;
    }
}
