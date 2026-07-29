<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects;

enum OnboardingTaskStatus: string
{
    case NotReady = 'not_ready';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case AwaitingClinicOwner = 'awaiting_clinic_owner';
    case AwaitingWebsiteDesigner = 'awaiting_website_designer';
    case Completed = 'completed';
    case Waived = 'waived';
    case Reopened = 'reopened';
    case Cancelled = 'cancelled';

    public function satisfiesDependency(): bool
    {
        return $this === self::Completed || $this === self::Waived;
    }
}
