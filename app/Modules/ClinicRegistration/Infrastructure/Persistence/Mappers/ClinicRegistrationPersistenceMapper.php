<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence\Mappers;

use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ClinicRegistrationProfile;
use App\Modules\ClinicRegistration\Domain\ValueObjects\CommercialSelectionReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\DeclarationAcceptance;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ProvisionedTenantReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationStatus;
use App\Modules\ClinicRegistration\Domain\ValueObjects\TenantId;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Records\ClinicRegistrationStorageRecord;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Records\DeclarationAcceptanceStorageRecord;

final class ClinicRegistrationPersistenceMapper
{
    public function registrationRecord(ClinicRegistration $registration): ClinicRegistrationStorageRecord
    {
        return new ClinicRegistrationStorageRecord(
            $registration->id->value,
            $registration->platformIdentity->value,
            $registration->status->value,
            $registration->profile->clinicName,
            $registration->profile->clinicEmail,
            $registration->profile->clinicPhone,
            $registration->profile->clinicAddress,
            $registration->commercialSelection->planOfferingReference,
            $registration->commercialSelection->billingOptionReference,
            $registration->commercialSelection->commercialSnapshotVersion,
            $registration->correlationReference,
            $registration->reservedTenantId?->value,
            $registration->provisionedTenant?->value,
            $registration->submittedAt,
            $registration->provisionedAt,
            $registration->cancelledAt,
            $registration->expiredAt,
            $registration->version(),
        );
    }

    /** @return list<DeclarationAcceptanceStorageRecord> */
    public function declarationRecords(ClinicRegistration $registration): array
    {
        return array_map(
            static fn (DeclarationAcceptance $declaration): DeclarationAcceptanceStorageRecord => new DeclarationAcceptanceStorageRecord(
                $registration->id->value,
                $declaration->key,
                $declaration->version,
                $declaration->acceptedAt,
            ),
            $registration->declarations,
        );
    }

    /** @param list<DeclarationAcceptanceStorageRecord> $declarations */
    public function toDomain(ClinicRegistrationStorageRecord $record, array $declarations): ClinicRegistration
    {
        return new ClinicRegistration(
            id: new RegistrationId($record->id),
            platformIdentity: new PlatformIdentityReference($record->platformIdentityId),
            status: RegistrationStatus::from($record->status),
            profile: new ClinicRegistrationProfile($record->clinicName, $record->clinicEmail, $record->clinicPhone, $record->clinicAddress),
            declarations: array_map(
                static fn (DeclarationAcceptanceStorageRecord $declaration): DeclarationAcceptance => new DeclarationAcceptance(
                    $declaration->declarationKey,
                    $declaration->declarationVersion,
                    $declaration->acceptedAt,
                ),
                $declarations,
            ),
            commercialSelection: new CommercialSelectionReference(
                $record->selectedPlanOfferingReference,
                $record->selectedBillingOptionReference,
                $record->commercialSnapshotVersion,
            ),
            correlationReference: $record->registrationCorrelationReference,
            reservedTenantId: $record->reservedTenantId === null ? null : new TenantId($record->reservedTenantId),
            provisionedTenant: $record->provisionedTenantReference === null ? null : new ProvisionedTenantReference($record->provisionedTenantReference),
            submittedAt: $record->submittedAt,
            provisionedAt: $record->provisionedAt,
            cancelledAt: $record->cancelledAt,
            expiredAt: $record->expiredAt,
            version: $record->version,
        );
    }
}
