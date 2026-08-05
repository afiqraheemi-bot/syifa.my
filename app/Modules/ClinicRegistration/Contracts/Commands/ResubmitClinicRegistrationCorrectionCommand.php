<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Commands;

use App\Modules\ClinicRegistration\Contracts\Data\DeclarationAcceptanceData;
use DateTimeImmutable;

final readonly class ResubmitClinicRegistrationCorrectionCommand
{
    /** @param list<DeclarationAcceptanceData> $declarations */
    public function __construct(
        public string $trackingCredential,
        public ?string $clinicName,
        public ?string $clinicEmail,
        public ?string $clinicPhone,
        public ?string $clinicAddress,
        public array $declarations,
        public int $expectedVersion,
        public DateTimeImmutable $occurredAt,
        public ?string $preferredSubdomain = null,
        public ?string $selectedWebsiteTemplate = null,
    ) {}
}
