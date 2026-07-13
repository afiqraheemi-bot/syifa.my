<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Assignments;

use DateTimeImmutable;

final readonly class WebsiteDesignerAssignmentData
{
    public function __construct(
        public string $assignmentId,
        public string $onboardingJobId,
        public string $platformIdentityId,
        public string $tenantId,
        public DateTimeImmutable $assignedAt,
    ) {}
}
