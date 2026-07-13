<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\WorkforceCredentials;

use DateTimeImmutable;

final readonly class PlatformWorkforceCredentialData
{
    public function __construct(
        public string $platformIdentityId,
        public string $normalizedEmail,
        public bool $emailVerified,
        public ?DateTimeImmutable $emailVerifiedAt,
        public string $accountStatus,
        public int $failedAttemptCount,
        public ?DateTimeImmutable $lockoutUntil,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
