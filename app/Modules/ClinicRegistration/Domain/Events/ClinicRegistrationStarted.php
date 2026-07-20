<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\Events;

use DateTimeImmutable;

final readonly class ClinicRegistrationStarted
{
    public function __construct(
        public string $registrationId,
        public string $platformIdentityId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
