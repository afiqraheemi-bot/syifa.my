<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects;

enum OnboardingJobStatus: string
{
    case Planned = 'planned';
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
