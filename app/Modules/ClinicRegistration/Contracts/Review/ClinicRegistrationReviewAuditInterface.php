<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Review;

use DateTimeImmutable;

interface ClinicRegistrationReviewAuditInterface
{
    public function record(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $registrationId,
        string $action,
        string $outcome,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void;
}
