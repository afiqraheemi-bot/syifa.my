<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Commands;

use DateTimeImmutable;

final readonly class ArchiveClinicRegistrationCommand
{
    public function __construct(
        public string $registrationId,
        public int $expectedVersion,
        public string $actorPlatformIdentityId,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
