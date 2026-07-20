<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\Events;

use DateTimeImmutable;

final readonly class ClinicRegistrationProvisioned
{
    public function __construct(
        public string $registrationId,
        public string $platformIdentityId,
        public string $correlationReference,
        public ?string $provisionedTenantReference,
        public DateTimeImmutable $occurredAt,
    ) {}
}
