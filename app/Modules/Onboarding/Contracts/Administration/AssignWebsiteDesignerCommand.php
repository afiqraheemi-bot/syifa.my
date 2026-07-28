<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Administration;

use DateTimeImmutable;

final readonly class AssignWebsiteDesignerCommand
{
    public function __construct(
        public string $onboardingJobId,
        public string $platformIdentityId,
        public int $expectedVersion,
        public string $actorPlatformIdentityId,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
