<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Commands;

use DateTimeImmutable;

final readonly class CompleteClinicRegistrationFromTrustedHandoffCommand
{
    public function __construct(
        public string $registrationCorrelationReference,
        public ?string $provisionedTenantReference,
        public string $trustedSource,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
