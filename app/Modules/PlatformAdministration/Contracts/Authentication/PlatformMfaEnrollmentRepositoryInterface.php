<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

use DateTimeImmutable;

interface PlatformMfaEnrollmentRepositoryInterface
{
    public function find(string $platformIdentityId): ?PlatformMfaEnrollmentData;

    public function enroll(
        string $platformIdentityId,
        string $encryptedTotpSecret,
        int $verifiedTimeStep,
        DateTimeImmutable $confirmedAt,
    ): PlatformMfaEnrollmentData;

    public function recordVerification(
        string $platformIdentityId,
        int $expectedVersion,
        int $verifiedTimeStep,
        DateTimeImmutable $verifiedAt,
    ): bool;
}
