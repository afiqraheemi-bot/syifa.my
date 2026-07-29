<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class OnboardingTaskStorageRecord
{
    public function __construct(
        public string $id,
        public string $onboardingJobId,
        public string $tenantId,
        public string $key,
        public string $title,
        public string $responsibility,
        public string $status,
        public bool $mandatory,
        public bool $blocking,
        public ?string $dependsOnTaskId,
        public ?DateTimeImmutable $dueAt,
        public ?string $evidenceReference,
        public ?string $note,
        public ?string $waiverReason,
        public DateTimeImmutable $taskCreatedAt,
        public DateTimeImmutable $taskUpdatedAt,
        public ?DateTimeImmutable $completedAt,
    ) {}
}
