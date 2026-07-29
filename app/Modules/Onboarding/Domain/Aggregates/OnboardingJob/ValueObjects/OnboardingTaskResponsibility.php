<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects;

enum OnboardingTaskResponsibility: string
{
    case ClinicOwner = 'clinic_owner';
    case WebsiteDesigner = 'website_designer';
}
