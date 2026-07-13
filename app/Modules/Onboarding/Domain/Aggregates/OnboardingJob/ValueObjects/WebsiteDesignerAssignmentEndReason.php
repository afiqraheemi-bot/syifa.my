<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects;

enum WebsiteDesignerAssignmentEndReason: string
{
    case Revoked = 'revoked';
    case Reassigned = 'reassigned';
    case OnboardingJobCompleted = 'onboarding_job_completed';
    case OnboardingJobCancelled = 'onboarding_job_cancelled';
}
