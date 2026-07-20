<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Records;

use DateTimeImmutable;

final readonly class AuditEntryStorageRecord
{
    /**
     * @param  array<string, scalar>  $safeMetadata
     */
    public function __construct(
        public string $auditEntryId,
        public DateTimeImmutable $occurredAt,
        public string $actorType,
        public ?string $actorIdentityId,
        public ?string $tenantId,
        public string $action,
        public string $targetType,
        public ?string $targetId,
        public string $outcome,
        public ?string $reasonCode,
        public string $correlationId,
        public array $safeMetadata,
    ) {}
}
