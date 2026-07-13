<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events;

use DateTimeImmutable;

final readonly class WebsiteDesignerAssigned
{
    public function __construct(
        public string $onboardingJobId,
        public string $websiteDesignerAssignmentId,
        public string $platformIdentityId,
        public string $tenantId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
