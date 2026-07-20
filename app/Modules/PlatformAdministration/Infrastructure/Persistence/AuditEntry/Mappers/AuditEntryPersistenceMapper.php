<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Mappers;

use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\Exceptions\InvalidAuditEntryValueException;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Exceptions\InvalidAuditEntryStorageStateException;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Records\AuditEntryStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use stdClass;

final class AuditEntryPersistenceMapper
{
    public function toStorageRecord(AuditEntry $auditEntry): AuditEntryStorageRecord
    {
        return new AuditEntryStorageRecord(
            $auditEntry->id->value,
            $auditEntry->occurredAt,
            $auditEntry->actorType->value,
            $auditEntry->actorIdentityId,
            $auditEntry->tenantId,
            $auditEntry->action,
            $auditEntry->targetType,
            $auditEntry->targetId,
            $auditEntry->outcome->value,
            $auditEntry->reasonCode,
            $auditEntry->correlationId,
            $auditEntry->safeMetadata,
        );
    }

    public function toDomain(stdClass $row): AuditEntry
    {
        try {
            return AuditEntry::reconstitute(
                new AuditEntryId($this->stringValue($row, 'audit_entry_id')),
                $this->dateTimeValue($row, 'occurred_at'),
                AuditActorType::from($this->stringValue($row, 'actor_type')),
                $this->nullableStringValue($row, 'actor_identity_id'),
                $this->nullableStringValue($row, 'tenant_id'),
                $this->stringValue($row, 'action'),
                $this->stringValue($row, 'target_type'),
                $this->nullableStringValue($row, 'target_id'),
                AuditOutcomeType::from($this->stringValue($row, 'outcome')),
                $this->nullableStringValue($row, 'reason_code'),
                $this->stringValue($row, 'correlation_id'),
                $this->decodeSafeMetadata($this->mixedValue($row, 'safe_metadata')),
            );
        } catch (InvalidAuditEntryValueException|JsonException|\ValueError $exception) {
            throw new InvalidAuditEntryStorageStateException(
                'Audit Entry storage row contains invalid persisted values.',
                0,
                $exception,
            );
        }
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidAuditEntryStorageStateException($field.' must be a string.');
        }

        return $value;
    }

    private function nullableStringValue(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidAuditEntryStorageStateException($field.' must be a string or null.');
        }

        return $value;
    }

    private function mixedValue(stdClass $row, string $field): mixed
    {
        return $row->{$field} ?? null;
    }

    private function dateTimeValue(stdClass $row, string $field): DateTimeImmutable
    {
        $value = $row->{$field} ?? null;

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (! is_string($value)) {
            throw new InvalidAuditEntryStorageStateException($field.' must be an instant.');
        }

        return new DateTimeImmutable($value);
    }

    /**
     * @return array<string, scalar>
     */
    private function decodeSafeMetadata(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof stdClass) {
            $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (! is_string($value)) {
            throw new InvalidAuditEntryStorageStateException('safe_metadata must be JSON serializable.');
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidAuditEntryStorageStateException('safe_metadata must decode to an array.');
        }

        /** @var array<string, scalar> $decoded */
        return $decoded;
    }
}
