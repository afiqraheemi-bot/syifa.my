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

    public function recordDesignerReassignment(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $previousAssignmentId,
        string $newAssignmentId,
        string $designerId,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void;

    public function recordJobLifecycleChange(
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $operation,
        ?string $reason,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void;
}
