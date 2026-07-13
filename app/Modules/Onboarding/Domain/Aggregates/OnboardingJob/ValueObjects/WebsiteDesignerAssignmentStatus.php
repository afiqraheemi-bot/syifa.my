<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects;

enum WebsiteDesignerAssignmentStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
}
