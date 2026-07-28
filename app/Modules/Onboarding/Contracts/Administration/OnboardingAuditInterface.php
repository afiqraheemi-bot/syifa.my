<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Administration;

use DateTimeImmutable;

interface OnboardingAuditInterface
{
    public function recordDesignerAssignment(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $assignmentId,
        string $designerId,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void;
}
