<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Commands;

use DateTimeImmutable;

final readonly class ExpireStaleClinicRegistrationCommand
{
    public function __construct(
        public string $registrationId,
        public int $expectedVersion,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
