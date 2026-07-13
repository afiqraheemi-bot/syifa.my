<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class OnboardingJobStorageRecord
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $websiteId,
        public string $status,
        public int $version,
        public DateTimeImmutable $jobCreatedAt,
        public ?DateTimeImmutable $awaitingInputsAt = null,
        public ?DateTimeImmutable $assignedAt = null,
        public ?DateTimeImmutable $inProgressAt = null,
        public ?DateTimeImmutable $blockedAt = null,
        public ?DateTimeImmutable $inReviewAt = null,
        public ?DateTimeImmutable $correctionRequiredAt = null,
        public ?DateTimeImmutable $readyForLaunchAt = null,
        public ?DateTimeImmutable $completedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?DateTimeImmutable $reopenedAt = null,
    ) {}
}
