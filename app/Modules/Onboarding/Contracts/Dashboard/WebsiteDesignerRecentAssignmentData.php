<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Dashboard;

use DateTimeImmutable;

final readonly class WebsiteDesignerRecentAssignmentData
{
    public function __construct(
        public string $assignmentId,
        public string $onboardingJobId,
        public string $tenantId,
        public string $status,
        public DateTimeImmutable $assignedAt,
    ) {}
}
