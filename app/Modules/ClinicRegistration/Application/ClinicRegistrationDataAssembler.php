<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Data\DeclarationAcceptanceData;
use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use DateTimeInterface;

final class ClinicRegistrationDataAssembler
{
    public function fromDomain(ClinicRegistration $registration): ClinicRegistrationData
    {
        return new ClinicRegistrationData(
            id: $registration->id->value,
            platformIdentityId: $registration->platformIdentity->value,
            status: $registration->status->value,
            clinicName: $registration->profile->clinicName,
            clinicEmail: $registration->profile->clinicEmail,
            clinicPhone: $registration->profile->clinicPhone,
            clinicAddress: $registration->profile->clinicAddress,
            selectedPlanOfferingReference: $registration->commercialSelection->planOfferingReference,
            selectedBillingOptionReference: $registration->commercialSelection->billingOptionReference,
            commercialSnapshotVersion: $registration->commercialSelection->commercialSnapshotVersion,
            registrationCorrelationReference: $registration->correlationReference,
            provisionedTenantReference: $registration->provisionedTenant?->value,
            submittedAt: $this->instant($registration->submittedAt),
            provisionedAt: $this->instant($registration->provisionedAt),
            cancelledAt: $this->instant($registration->cancelledAt),
            expiredAt: $this->instant($registration->expiredAt),
            version: $registration->version(),
            declarations: array_map(
                static fn ($declaration): DeclarationAcceptanceData => new DeclarationAcceptanceData(
                    $declaration->key,
                    $declaration->version,
                    $declaration->acceptedAt->format(DateTimeInterface::ATOM),
                ),
                $registration->declarations,
            ),
        );
    }

    private function instant(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime?->format(DateTimeInterface::ATOM);
    }
}
