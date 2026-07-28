<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Commands;

use DateTimeImmutable;

final readonly class DecideClinicRegistrationCommand
{
    public function __construct(
        public string $registrationId,
        public string $decisionId,
        public string $outcome,
        public string $reasonCategory,
        public ?string $correctionInstructions,
        public int $expectedVersion,
        public string $reviewerPlatformIdentityId,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
