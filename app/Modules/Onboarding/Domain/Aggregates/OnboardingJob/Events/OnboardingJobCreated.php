<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Events;

use DateTimeImmutable;

final readonly class OnboardingJobCreated
{
    public function __construct(
        public string $onboardingJobId,
        public string $tenantId,
        public string $websiteId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
