<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Data;

final readonly class ClinicRegistrationData
{
    /**
     * @param  list<DeclarationAcceptanceData>  $declarations
     * @param  list<RegistrationDecisionData>  $decisions
     */
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
        public ?string $reservedTenantId,
        public ?string $provisionedTenantReference,
        public ?string $submittedAt,
        public ?string $provisionedAt,
        public ?string $cancelledAt,
        public ?string $expiredAt,
        public int $version,
        public array $declarations,
        public array $decisions = [],
    ) {}
}
