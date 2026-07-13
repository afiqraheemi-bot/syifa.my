<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Records;

use DateTimeImmutable;

final readonly class PlatformWorkforceCredentialStorageRecord
{
    public function __construct(
        public string $platformIdentityId,
        public string $normalizedEmail,
        public string $passwordHash,
        public string $emailVerificationStatus,
        public ?DateTimeImmutable $emailVerifiedAt,
        public string $accountStatus,
        public int $failedAttemptCount,
        public ?DateTimeImmutable $lockoutUntil,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
