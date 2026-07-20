<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class ClinicRegistrationStorageRecord
{
    public function __construct(
        public string $id,
        public string $platformIdentityId,
        public string $status,
        public ?string $clinicName,
        public ?string $clinicEmail,
        public ?string $clinicPhone,
        public ?string $clinicAddress,
        public ?string $selectedPlanOfferingReference,
        public ?string $selectedBillingOptionReference,
        public ?string $commercialSnapshotVersion,
        public string $registrationCorrelationReference,
        public ?string $provisionedTenantReference,
        public ?DateTimeImmutable $submittedAt,
        public ?DateTimeImmutable $provisionedAt,
        public ?DateTimeImmutable $cancelledAt,
        public ?DateTimeImmutable $expiredAt,
        public int $version,
    ) {}
}
