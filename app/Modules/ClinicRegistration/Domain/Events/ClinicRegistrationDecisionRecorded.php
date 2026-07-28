<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\Events;

use DateTimeImmutable;

final readonly class ClinicRegistrationDecisionRecorded
{
    public function __construct(
        public string $registrationId,
        public string $decisionId,
        public string $outcome,
        public string $reviewerPlatformIdentityId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
