<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Dashboard;

use DateTimeImmutable;

final readonly class WebsiteDesignerQueueJobData
{
    public function __construct(
        public string $assignmentId,
        public string $onboardingJobId,
        public string $tenantId,
        public string $websiteId,
        public string $status,
        public DateTimeImmutable $assignedAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
