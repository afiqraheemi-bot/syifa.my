<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\Events;

use DateTimeImmutable;

final readonly class ClinicRegistrationReviewStarted
{
    public function __construct(
        public string $registrationId,
        public string $reviewerPlatformIdentityId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
