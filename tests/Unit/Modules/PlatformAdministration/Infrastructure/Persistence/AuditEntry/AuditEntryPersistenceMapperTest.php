<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry;

use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Exceptions\InvalidAuditEntryStorageStateException;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Mappers\AuditEntryPersistenceMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;

final class AuditEntryPersistenceMapperTest extends TestCase
{
    public function test_it_maps_domain_audit_entries_to_storage_records_and_back(): void
    {
        $mapper = new AuditEntryPersistenceMapper;
        $entry = $this->entry();
        $record = $mapper->toStorageRecord($entry);

        self::assertSame($entry->id->value, $record->auditEntryId);
        self::assertSame($entry->actorType->value, $record->actorType);
        self::assertSame($entry->safeMetadata, $record->safeMetadata);

        $row = (object) [
            'audit_entry_id' => $record->auditEntryId,
            'occurred_at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
            'actor_type' => $record->actorType,
            'actor_identity_id' => $record->actorIdentityId,
            'tenant_id' => $record->tenantId,
            'action' => $record->action,
            'target_type' => $record->targetType,
            'target_id' => $record->targetId,
            'outcome' => $record->outcome,
            'reason_code' => $record->reasonCode,
            'correlation_id' => $record->correlationId,
            'safe_metadata' => json_encode($record->safeMetadata, JSON_THROW_ON_ERROR),
        ];

        $reconstituted = $mapper->toDomain($row);

        self::assertSame($entry->id->value, $reconstituted->id->value);
        self::assertSame($entry->occurredAt->format('Y-m-d H:i:s.uP'), $reconstituted->occurredAt->format('Y-m-d H:i:s.uP'));
        self::assertSame($entry->safeMetadata, $reconstituted->safeMetadata);
    }

    public function test_it_rejects_invalid_storage_state(): void
    {
        $this->expectException(InvalidAuditEntryStorageStateException::class);

        $mapper = new AuditEntryPersistenceMapper;
        $row = new stdClass;
        $row->audit_entry_id = 'not-a-uuid';
        $row->occurred_at = '2026-07-20T03:30:00Z';
        $row->actor_type = 'platform_identity';
        $row->actor_identity_id = '00000000-0000-4000-8000-000000000101';
        $row->tenant_id = null;
        $row->action = 'platform.authorization.evaluate';
        $row->target_type = 'platform_permission';
        $row->target_id = 'commercial-catalogue.plan.annual';
        $row->outcome = 'denied';
        $row->reason_code = 'authorization_denied';
        $row->correlation_id = '00000000-0000-4000-8000-000000000402';
        $row->safe_metadata = '{"actor_role":"super_admin"}';

        $mapper->toDomain($row);
    }

    private function entry(): AuditEntry
    {
        return AuditEntry::record(
            new AuditEntryId('00000000-0000-4000-8000-000000000403'),
            new DateTimeImmutable('2026-07-20T03:30:00Z'),
            AuditActorType::PlatformIdentity,
            '00000000-0000-4000-8000-000000000101',
            null,
            'platform.authorization.evaluate',
            'platform_permission',
            'commercial-catalogue.plan.annual',
            AuditOutcomeType::Denied,
            'authorization_denied',
            '00000000-0000-4000-8000-000000000404',
            ['actor_role' => 'super_admin', 'resource_label' => 'commercial catalogue'],
        );
    }
}
