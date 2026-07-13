<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class WebsiteDesignerAssignmentStorageRecord
{
    public function __construct(
        public string $id,
        public string $onboardingJobId,
        public string $tenantId,
        public string $platformIdentityId,
        public string $assignmentStatus,
        public DateTimeImmutable $assignedAt,
        public ?DateTimeImmutable $endedAt,
        public ?string $endReason,
    ) {}
}
