<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresAuditEntryReadAdapter implements AuditEntryReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function search(
        ?string $action,
        ?string $outcome,
        ?string $actorType,
        ?string $tenantId,
        ?string $correlationId,
    ): array {
        $entries = $this->connection->table('audit_entries')
            ->when($action !== null, static fn ($query) => $query->where('action', 'ilike', '%'.$action.'%'))
            ->when($outcome !== null, static fn ($query) => $query->where('outcome', $outcome))
            ->when($actorType !== null, static fn ($query) => $query->where('actor_type', $actorType))
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($correlationId !== null, static fn ($query) => $query->where('correlation_id', $correlationId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('audit_entry_id')
            ->limit(100)
            ->get([
                'audit_entry_id',
                'occurred_at',
                'actor_type',
                'actor_identity_id',
                'tenant_id',
                'action',
                'target_type',
                'target_id',
                'outcome',
                'reason_code',
                'correlation_id',
            ])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->audit_entry_id,
                'occurredAt' => (string) $row->occurred_at,
                'actorType' => (string) $row->actor_type,
                'actorIdentityId' => $row->actor_identity_id === null ? null : (string) $row->actor_identity_id,
                'tenantId' => $row->tenant_id === null ? null : (string) $row->tenant_id,
                'action' => (string) $row->action,
                'targetType' => (string) $row->target_type,
                'targetId' => (string) $row->target_id,
                'outcome' => (string) $row->outcome,
                'reasonCode' => $row->reason_code === null ? null : (string) $row->reason_code,
                'correlationId' => (string) $row->correlation_id,
            ])
            ->all();

        return ['entries' => array_values($entries)];
    }
}
