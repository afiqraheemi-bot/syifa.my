<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Persistence\Mappers;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Entities\ClinicOwnerAuthority;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityStatus;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerCredentialState;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantAdminRoutingLabel;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantLifecycleStatus;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantLifecycleTimestamps;
use App\Modules\TenantManagement\Infrastructure\Persistence\Records\ClinicOwnerAuthorityStorageRecord;
use App\Modules\TenantManagement\Infrastructure\Persistence\Records\TenantStorageRecord;

final class TenantPersistenceMapper
{
    /** @param list<ClinicOwnerAuthorityStorageRecord> $authorityRecords */
    public function toDomain(
        TenantStorageRecord $tenantRecord,
        array $authorityRecords,
    ): Tenant {
        $tenantId = new TenantId($tenantRecord->id);
        $authorities = [];

        foreach ($authorityRecords as $record) {
            $authorities[] = new ClinicOwnerAuthority(
                new ClinicOwnerAuthorityId($record->id),
                new TenantId($record->tenantId),
                new ClinicOwnerIdentity(
                    new ClinicOwnerIdentityId($record->clinicOwnerIdentityId),
                    new ClinicOwnerEmail($record->email),
                    new ClinicOwnerName($record->name),
                ),
                ClinicOwnerAuthorityStatus::from($record->authorityStatus),
                $record->establishedAt,
                $record->revokedAt,
                new ClinicOwnerCredentialState(
                    $record->passwordHash,
                    $record->emailVerifiedAt,
                    $record->failedAttemptCount,
                    $record->lockoutUntil,
                    $record->credentialVersion,
                ),
            );
        }

        return Tenant::reconstitute(
            $tenantId,
            TenantLifecycleStatus::from($tenantRecord->status),
            new TenantLifecycleTimestamps(
                $tenantRecord->provisionedAt,
                $tenantRecord->activatedAt,
                $tenantRecord->suspendedAt,
                $tenantRecord->reactivatedAt,
                $tenantRecord->offboardingStartedAt,
                $tenantRecord->deletedOrAnonymizedAt,
            ),
            $authorities,
            $tenantRecord->version,
            $tenantRecord->adminRoutingLabel === null
                ? null
                : new TenantAdminRoutingLabel($tenantRecord->adminRoutingLabel),
        );
    }

    public function tenantRecord(Tenant $tenant): TenantStorageRecord
    {
        return new TenantStorageRecord(
            $tenant->id->value,
            $tenant->status()->value,
            $tenant->version(),
            $tenant->lifecycleTimestamps()->provisionedAt,
            $tenant->lifecycleTimestamps()->activatedAt,
            $tenant->lifecycleTimestamps()->suspendedAt,
            $tenant->lifecycleTimestamps()->reactivatedAt,
            $tenant->lifecycleTimestamps()->offboardingStartedAt,
            $tenant->lifecycleTimestamps()->deletedOrAnonymizedAt,
            $tenant->adminRoutingLabel()?->value,
        );
    }

    /** @return list<ClinicOwnerAuthorityStorageRecord> */
    public function authorityRecords(Tenant $tenant): array
    {
        $records = [];

        foreach ($tenant->clinicOwnerAuthorityHistory() as $authority) {
            $records[] = new ClinicOwnerAuthorityStorageRecord(
                $authority->id->value,
                $authority->tenantId->value,
                $authority->clinicOwnerIdentity->id->value,
                $authority->clinicOwnerIdentity->email->value,
                $authority->clinicOwnerIdentity->name->value,
                $authority->status->value,
                $authority->establishedAt,
                $authority->revokedAt,
                $authority->credentialState()->passwordHashForPersistence(),
                $authority->credentialState()->isEmailVerified() ? 'verified' : 'unverified',
                $authority->credentialState()->emailVerifiedAt,
                $authority->credentialState()->failedAttemptCount,
                $authority->credentialState()->lockoutUntil,
                $authority->credentialState()->credentialVersion,
            );
        }

        return $records;
    }
}
