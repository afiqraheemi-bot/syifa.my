<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

use DateTimeImmutable;

final readonly class PlatformMfaEnrollmentData
{
    public function __construct(
        public string $platformIdentityId,
        public string $encryptedTotpSecret,
        public DateTimeImmutable $confirmedAt,
        public ?int $lastVerifiedTimeStep,
        public int $version,
    ) {}
}
