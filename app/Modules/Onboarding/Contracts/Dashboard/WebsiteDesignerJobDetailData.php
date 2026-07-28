<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Dashboard;

use DateTimeImmutable;

final readonly class WebsiteDesignerJobDetailData
{
    /**
     * @param  array<string, DateTimeImmutable|null>  $lifecycle
     */
    public function __construct(
        public string $assignmentId,
        public string $onboardingJobId,
        public string $tenantId,
        public string $websiteId,
        public string $status,
        public int $version,
        public DateTimeImmutable $assignedAt,
        public DateTimeImmutable $updatedAt,
        public array $lifecycle,
    ) {}
}
