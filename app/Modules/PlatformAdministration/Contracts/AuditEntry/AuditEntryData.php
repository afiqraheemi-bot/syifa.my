<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\AuditEntry;

use DateTimeImmutable;

final readonly class AuditEntryData
{
    /**
     * @param  array<string, scalar>  $safeMetadata
     */
    public function __construct(
        public string $auditEntryId,
        public DateTimeImmutable $occurredAt,
        public AuditActorData $actor,
        public ?string $tenantId,
        public string $action,
        public AuditTargetData $target,
        public AuditOutcomeData $outcome,
        public string $correlationId,
        public array $safeMetadata,
    ) {}
}
