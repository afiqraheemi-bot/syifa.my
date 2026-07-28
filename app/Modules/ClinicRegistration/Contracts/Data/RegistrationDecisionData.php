<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Data;

final readonly class RegistrationDecisionData
{
    public function __construct(
        public string $id,
        public string $outcome,
        public string $reasonCategory,
        public ?string $correctionInstructions,
        public string $decidedByPlatformIdentityId,
        public string $decidedAt,
        public ?string $supersededAt,
    ) {}
}
