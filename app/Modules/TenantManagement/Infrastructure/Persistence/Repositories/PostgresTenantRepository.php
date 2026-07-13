<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Persistence\Repositories;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\StaleTenantWriteException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Infrastructure\Persistence\Exceptions\InvalidTenantStorageStateException;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Records\ClinicOwnerAuthorityStorageRecord;
use App\Modules\TenantManagement\Infrastructure\Persistence\Records\TenantStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresTenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly TenantPersistenceMapper $mapper,
    ) {}

    public function find(TenantId $tenantId): ?Tenant
    {
        $tenantRow = $this->connection
            ->table('tenants')
            ->where('id', $tenantId->value)
            ->first();

        if ($tenantRow === null) {
            return null;
        }

        $authorityRows = $this->connection
            ->table('clinic_owner_authorities')
            ->where('tenant_id', $tenantId->value)
            ->orderBy('established_at')
            ->orderBy('id')
            ->get();

        $authorityRecords = [];

        foreach ($authorityRows as $authorityRow) {
            $authorityRecords[] = $this->authorityRecordFromRow($authorityRow);
        }

        return $this->mapper->toDomain(
            $this->tenantRecordFromRow($tenantRow),
            $authorityRecords,
        );
    }

    public function save(Tenant $tenant): void
    {
        $persistedVersion = $this->connection->transaction(
            fn (): int => $tenant->version() === 0
                ? $this->insert($tenant)
                : $this->update($tenant),
        );

        if (! is_int($persistedVersion)) {
            throw new InvalidTenantStorageStateException('Tenant transaction did not return a persistence version.');
        }

        $tenant->synchronizePersistenceVersion($persistedVersion);
    }

    private function insert(Tenant $tenant): int
    {
        $record = $this->mapper->tenantRecord($tenant);

        if ($this->connection->table('tenants')->where('id', $record->id)->exists()) {
            throw new StaleTenantWriteException('Tenant already exists; the aggregate version is stale.');
        }

        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table('tenants')->insert([
            'id' => $record->id,
            'status' => $record->status,
            'version' => 1,
            'admin_routing_label' => $record->adminRoutingLabel,
            'provisioned_at' => $this->databaseTimestamp($record->provisionedAt),
            'activated_at' => $this->nullableDatabaseTimestamp($record->activatedAt),
            'suspended_at' => $this->nullableDatabaseTimestamp($record->suspendedAt),
            'reactivated_at' => $this->nullableDatabaseTimestamp($record->reactivatedAt),
            'offboarding_started_at' => $this->nullableDatabaseTimestamp($record->offboardingStartedAt),
            'deleted_or_anonymized_at' => $this->nullableDatabaseTimestamp($record->deletedOrAnonymizedAt),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->persistAuthorityChanges($tenant, $now);

        return 1;
    }

    private function update(Tenant $tenant): int
    {
        $record = $this->mapper->tenantRecord($tenant);
        $newVersion = $record->version + 1;
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $affected = $this->connection
            ->table('tenants')
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                'status' => $record->status,
                'version' => $newVersion,
                'admin_routing_label' => $record->adminRoutingLabel,
                'activated_at' => $this->nullableDatabaseTimestamp($record->activatedAt),
                'suspended_at' => $this->nullableDatabaseTimestamp($record->suspendedAt),
                'reactivated_at' => $this->nullableDatabaseTimestamp($record->reactivatedAt),
                'offboarding_started_at' => $this->nullableDatabaseTimestamp($record->offboardingStartedAt),
                'deleted_or_anonymized_at' => $this->nullableDatabaseTimestamp($record->deletedOrAnonymizedAt),
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            throw new StaleTenantWriteException('Tenant write rejected because its aggregate version is stale.');
        }

        $this->persistAuthorityChanges($tenant, $now);

        return $newVersion;
    }

    private function persistAuthorityChanges(Tenant $tenant, string $now): void
    {
        $storedRows = $this->connection
            ->table('clinic_owner_authorities')
            ->where('tenant_id', $tenant->id->value)
            ->lockForUpdate()
            ->get();
        $stored = [];

        foreach ($storedRows as $storedRow) {
            $record = $this->authorityRecordFromRow($storedRow);
            $stored[$record->id] = $record;
        }

        $aggregateRecords = [];

        foreach ($this->mapper->authorityRecords($tenant) as $record) {
            if ($record->tenantId !== $tenant->id->value) {
                throw new InvalidTenantStorageStateException('Clinic Owner Authority has conflicting Tenant ownership.');
            }

            $aggregateRecords[$record->id] = $record;
            $storedRecord = $stored[$record->id] ?? null;

            if ($storedRecord === null) {
                $this->insertAuthority($record, $now);

                continue;
            }

            $this->persistExistingAuthorityTransition($storedRecord, $record, $now);
        }

        foreach ($stored as $storedRecord) {
            if (! isset($aggregateRecords[$storedRecord->id])) {
                throw new InvalidTenantStorageStateException(
                    'Clinic Owner Authority history cannot be removed from its Tenant.',
                );
            }
        }
    }

    private function insertAuthority(ClinicOwnerAuthorityStorageRecord $record, string $now): void
    {
        $this->connection->table('clinic_owner_authorities')->insert([
            'id' => $record->id,
            'tenant_id' => $record->tenantId,
            'clinic_owner_identity_id' => $record->clinicOwnerIdentityId,
            'email' => $record->email,
            'name' => $record->name,
            'authority_status' => $record->authorityStatus,
            'established_at' => $this->databaseTimestamp($record->establishedAt),
            'revoked_at' => $record->revokedAt === null
                ? null
                : $this->databaseTimestamp($record->revokedAt),
            'password_hash' => $record->passwordHash,
            'email_verification_status' => $record->emailVerificationStatus,
            'email_verified_at' => $this->nullableDatabaseTimestamp($record->emailVerifiedAt),
            'failed_attempt_count' => $record->failedAttemptCount,
            'lockout_until' => $this->nullableDatabaseTimestamp($record->lockoutUntil),
            'credential_version' => $record->credentialVersion,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function persistExistingAuthorityTransition(
        ClinicOwnerAuthorityStorageRecord $stored,
        ClinicOwnerAuthorityStorageRecord $aggregate,
        string $now,
    ): void {
        if ($stored->tenantId !== $aggregate->tenantId
            || $stored->clinicOwnerIdentityId !== $aggregate->clinicOwnerIdentityId
            || $stored->email !== $aggregate->email
            || $stored->name !== $aggregate->name
            || $stored->establishedAt != $aggregate->establishedAt) {
            throw new InvalidTenantStorageStateException(
                'Immutable Clinic Owner Authority history cannot be rewritten.',
            );
        }

        $credentialChanged = $stored->passwordHash !== $aggregate->passwordHash
            || $stored->emailVerificationStatus !== $aggregate->emailVerificationStatus
            || $stored->emailVerifiedAt != $aggregate->emailVerifiedAt
            || $stored->failedAttemptCount !== $aggregate->failedAttemptCount
            || $stored->lockoutUntil != $aggregate->lockoutUntil
            || $stored->credentialVersion !== $aggregate->credentialVersion;
        $authorityChanged = $stored->authorityStatus !== $aggregate->authorityStatus
            || $stored->revokedAt != $aggregate->revokedAt;

        if (! $credentialChanged && ! $authorityChanged) {
            return;
        }

        if ($authorityChanged && ($stored->authorityStatus !== 'active'
            || $aggregate->authorityStatus !== 'revoked'
            || $aggregate->revokedAt === null)) {
            throw new InvalidTenantStorageStateException(
                'Only an active-to-revoked Clinic Owner Authority transition may update history.',
            );
        }

        if ($aggregate->credentialVersion < $stored->credentialVersion
            || ($stored->passwordHash !== $aggregate->passwordHash
                && $aggregate->credentialVersion <= $stored->credentialVersion)
            || ($stored->emailVerifiedAt !== null && $stored->emailVerifiedAt != $aggregate->emailVerifiedAt)) {
            throw new InvalidTenantStorageStateException(
                'Clinic Owner credential history cannot be reversed or rewritten.',
            );
        }

        $affected = $this->connection
            ->table('clinic_owner_authorities')
            ->where('tenant_id', $aggregate->tenantId)
            ->where('id', $aggregate->id)
            ->update([
                'authority_status' => $aggregate->authorityStatus,
                'revoked_at' => $this->nullableDatabaseTimestamp($aggregate->revokedAt),
                'password_hash' => $aggregate->passwordHash,
                'email_verification_status' => $aggregate->emailVerificationStatus,
                'email_verified_at' => $this->nullableDatabaseTimestamp($aggregate->emailVerifiedAt),
                'failed_attempt_count' => $aggregate->failedAttemptCount,
                'lockout_until' => $this->nullableDatabaseTimestamp($aggregate->lockoutUntil),
                'credential_version' => $aggregate->credentialVersion,
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            throw new InvalidTenantStorageStateException('Clinic Owner Authority state update was not persisted.');
        }
    }

    private function tenantRecordFromRow(stdClass $row): TenantStorageRecord
    {
        return new TenantStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'status'),
            $this->integerValue($row, 'version'),
            $this->dateTimeValue($row->provisioned_at ?? null, 'provisioned_at'),
            $this->nullableDateTimeValue($row->activated_at ?? null, 'activated_at'),
            $this->nullableDateTimeValue($row->suspended_at ?? null, 'suspended_at'),
            $this->nullableDateTimeValue($row->reactivated_at ?? null, 'reactivated_at'),
            $this->nullableDateTimeValue($row->offboarding_started_at ?? null, 'offboarding_started_at'),
            $this->nullableDateTimeValue($row->deleted_or_anonymized_at ?? null, 'deleted_or_anonymized_at'),
            $this->nullableStringValue($row, 'admin_routing_label'),
        );
    }

    private function authorityRecordFromRow(stdClass $row): ClinicOwnerAuthorityStorageRecord
    {
        $revokedAt = $row->revoked_at ?? null;

        return new ClinicOwnerAuthorityStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'tenant_id'),
            $this->stringValue($row, 'clinic_owner_identity_id'),
            $this->stringValue($row, 'email'),
            $this->stringValue($row, 'name'),
            $this->stringValue($row, 'authority_status'),
            $this->dateTimeValue($row->established_at ?? null, 'established_at'),
            $revokedAt === null ? null : $this->dateTimeValue($revokedAt, 'revoked_at'),
            $this->nullableStringValue($row, 'password_hash'),
            $this->stringValue($row, 'email_verification_status'),
            $this->nullableDateTimeValue($row->email_verified_at ?? null, 'email_verified_at'),
            $this->integerValue($row, 'failed_attempt_count'),
            $this->nullableDateTimeValue($row->lockout_until ?? null, 'lockout_until'),
            $this->integerValue($row, 'credential_version'),
        );
    }

    private function nullableStringValue(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new InvalidTenantStorageStateException(sprintf('Tenant storage field %s must be a string or null.', $field));
        }

        return $value;
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidTenantStorageStateException(sprintf('Tenant storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function integerValue(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidTenantStorageStateException(sprintf('Tenant storage field %s must be an integer.', $field));
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidTenantStorageStateException(sprintf('Tenant storage field %s must be a timestamp.', $field));
    }

    private function nullableDateTimeValue(mixed $value, string $field): ?DateTimeImmutable
    {
        return $value === null ? null : $this->dateTimeValue($value, $field);
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }

    private function nullableDatabaseTimestamp(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime === null ? null : $this->databaseTimestamp($dateTime);
    }
}
