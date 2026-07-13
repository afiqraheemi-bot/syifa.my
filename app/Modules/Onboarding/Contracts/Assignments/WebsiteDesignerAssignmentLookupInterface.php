<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Assignments;

interface WebsiteDesignerAssignmentLookupInterface
{
    public function findActiveForTenant(
        string $platformIdentityId,
        string $tenantId,
    ): ?WebsiteDesignerAssignmentData;

    public function findActiveForOnboardingJob(
        string $onboardingJobId,
    ): ?WebsiteDesignerAssignmentData;
}
