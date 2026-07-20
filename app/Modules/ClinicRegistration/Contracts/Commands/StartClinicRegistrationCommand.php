<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Commands;

use DateTimeImmutable;

final readonly class StartClinicRegistrationCommand
{
    public function __construct(
        public string $platformIdentityId,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
