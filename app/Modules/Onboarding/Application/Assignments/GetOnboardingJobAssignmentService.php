<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Assignments;

use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentData;
use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentLookupInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;

final readonly class GetOnboardingJobAssignmentService
{
    public function __construct(private WebsiteDesignerAssignmentLookupInterface $assignments) {}

    public function execute(OnboardingJobId $onboardingJobId): ?WebsiteDesignerAssignmentData
    {
        return $this->assignments->findActiveForOnboardingJob($onboardingJobId->value);
    }
}
