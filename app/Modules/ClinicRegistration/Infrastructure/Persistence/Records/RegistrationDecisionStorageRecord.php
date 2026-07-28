<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class RegistrationDecisionStorageRecord
{
    public function __construct(
        public string $id,
        public string $registrationId,
        public string $outcome,
        public string $reasonCategory,
        public ?string $correctionInstructions,
        public string $decidedByPlatformIdentityId,
        public DateTimeImmutable $decidedAt,
        public ?DateTimeImmutable $supersededAt,
    ) {}
}
