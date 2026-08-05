<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects;

enum OnboardingJobStatus: string
{
    case Planned = 'planned';

    /**
     * Reachable only through {@see OnboardingJob::awaitInputs()}. The sole
     * automated creation path today (`ProvisionOnboardingJobService`) always
     * completes the `clinic_inputs` task immediately from the already-approved
     * Clinic Registration, so this status is never produced in production yet
     * — it is reserved for a future manual/administrative job-creation path
     * that starts before clinic inputs are known. Dashboard/reporting code
     * that counts this bucket (e.g. `pendingContentCollection`) will
     * legitimately read zero until that path exists; that is not a bug.
     */
    case AwaitingInputs = 'awaiting_inputs';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case InReview = 'in_review';
    case CorrectionRequired = 'correction_required';
    case ReadyForLaunch = 'ready_for_launch';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Reopened = 'reopened';
}
