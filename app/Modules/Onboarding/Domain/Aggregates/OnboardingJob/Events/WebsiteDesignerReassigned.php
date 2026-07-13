<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events;

use DateTimeImmutable;

final readonly class WebsiteDesignerReassigned
{
    public function __construct(
        public string $onboardingJobId,
        public string $previousWebsiteDesignerAssignmentId,
        public string $currentWebsiteDesignerAssignmentId,
        public string $tenantId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
