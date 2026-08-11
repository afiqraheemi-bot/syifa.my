<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Commands;

use DateTimeImmutable;

final readonly class UpdateClinicRegistrationByAdministratorCommand
{
    public function __construct(
        public string $registrationId,
        public string $clinicName,
        public string $clinicEmail,
        public string $clinicPhone,
        public string $clinicAddress,
        public int $expectedVersion,
        public string $actorPlatformIdentityId,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
