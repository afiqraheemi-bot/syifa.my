<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\Events;

use DateTimeImmutable;

final readonly class ClinicRegistrationResubmitted
{
    public function __construct(
        public string $registrationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
