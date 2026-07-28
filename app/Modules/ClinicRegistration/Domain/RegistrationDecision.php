<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain;

use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationValueException;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationDecisionOutcome;
use DateTimeImmutable;

final class RegistrationDecision
{
    public function __construct(
        public readonly string $id,
        public readonly RegistrationDecisionOutcome $outcome,
        public readonly string $reasonCategory,
        public readonly ?string $correctionInstructions,
        public readonly string $decidedByPlatformIdentityId,
        public readonly DateTimeImmutable $decidedAt,
        public ?DateTimeImmutable $supersededAt = null,
    ) {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id)) {
            throw new InvalidClinicRegistrationValueException('Registration decision id must be a UUID.');
        }
        if (trim($reasonCategory) === '' || mb_strlen($reasonCategory) > 100) {
            throw new InvalidClinicRegistrationValueException('Registration decision reason category is required and must not exceed 100 characters.');
        }
        if ($outcome === RegistrationDecisionOutcome::CorrectionRequested
            && ($correctionInstructions === null || trim($correctionInstructions) === '')) {
            throw new InvalidClinicRegistrationValueException('Correction instructions are required when a correction is requested.');
        }
        if ($correctionInstructions !== null && mb_strlen($correctionInstructions) > 2000) {
            throw new InvalidClinicRegistrationValueException('Correction instructions must not exceed 2000 characters.');
        }
    }

    public function supersede(DateTimeImmutable $occurredAt): void
    {
        if ($this->supersededAt === null) {
            $this->supersededAt = $occurredAt;
        }
    }
}
