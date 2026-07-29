<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Tasks;

use DateTimeImmutable;

final readonly class ProgressOnboardingTaskCommand
{
    public function __construct(
        public string $onboardingJobId,
        public string $taskId,
        public string $operation,
        public int $expectedVersion,
        public string $actorId,
        public string $actorRole,
        public ?string $actorTenantId,
        public ?string $evidenceReference,
        public ?string $note,
        public ?string $waiverReason,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
