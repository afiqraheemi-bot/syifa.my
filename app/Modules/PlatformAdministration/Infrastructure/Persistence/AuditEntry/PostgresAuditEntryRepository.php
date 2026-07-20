<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRepositoryInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Mappers\AuditEntryPersistenceMapper;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresAuditEntryRepository implements AuditEntryRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private AuditEntryPersistenceMapper $mapper,
    ) {}

    public function append(AuditEntry $auditEntry): AuditEntry
    {
        $record = $this->mapper->toStorageRecord($auditEntry);

        $this->connection->table('audit_entries')->insert([
            'audit_entry_id' => $record->auditEntryId,
            'occurred_at' => $this->timestamp($record->occurredAt),
            'actor_type' => $record->actorType,
            'actor_identity_id' => $record->actorIdentityId,
            'tenant_id' => $record->tenantId,
            'action' => $record->action,
            'target_type' => $record->targetType,
            'target_id' => $record->targetId,
            'outcome' => $record->outcome,
            'reason_code' => $record->reasonCode,
            'correlation_id' => $record->correlationId,
            'safe_metadata' => json_encode($record->safeMetadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return $auditEntry;
    }

    private function timestamp(DateTimeImmutable $instant): string
    {
        return $instant->format('Y-m-d H:i:s.uP');
    }
}
