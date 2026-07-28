<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\WebsiteApproval;

use DateTimeImmutable;

interface WebsiteApprovalAuditInterface
{
    public function recordWebsiteApprovalRequested(
        string $actorId,
        string $tenantId,
        string $jobId,
        string $approvalId,
        int $websiteVersion,
        int $draftVersion,
        int $resultingJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void;

    public function recordWebsiteApprovalDecision(
        string $actorId,
        string $tenantId,
        string $jobId,
        string $approvalId,
        string $decision,
        int $resultingJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void;
}
