<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Commands;

use App\Modules\ClinicRegistration\Contracts\Data\DeclarationAcceptanceData;
use DateTimeImmutable;

final readonly class UpdateClinicRegistrationDraftCommand
{
    /**
     * @param  list<DeclarationAcceptanceData>  $declarations
     */
    public function __construct(
        public string $platformIdentityId,
        public ?string $clinicName,
        public ?string $clinicEmail,
        public ?string $clinicPhone,
        public ?string $clinicAddress,
        public ?string $selectedPlanOfferingReference,
        public ?string $selectedBillingOptionReference,
        public ?string $commercialSnapshotVersion,
        public int $expectedVersion,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
        public array $declarations,
    ) {}
}
