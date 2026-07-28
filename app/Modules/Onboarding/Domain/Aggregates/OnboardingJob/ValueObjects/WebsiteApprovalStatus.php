<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects;

enum WebsiteApprovalStatus: string
{
    case Requested = 'requested';
    case CorrectionRequested = 'correction_requested';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
}
