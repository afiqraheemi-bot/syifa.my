<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Administration;

use DateTimeImmutable;

final readonly class ManageOnboardingJobLifecycleCommand
{
    public function __construct(
        public string $onboardingJobId,
        public string $operation,
        public ?string $reason,
        public int $expectedVersion,
        public string $actorPlatformIdentityId,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
