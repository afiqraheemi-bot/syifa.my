<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Commands;

use DateTimeImmutable;

final readonly class StartClinicRegistrationReviewCommand
{
    public function __construct(
        public string $registrationId,
        public int $expectedVersion,
        public string $reviewerPlatformIdentityId,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
