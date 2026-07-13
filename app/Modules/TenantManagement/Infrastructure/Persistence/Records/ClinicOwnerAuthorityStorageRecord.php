<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class ClinicOwnerAuthorityStorageRecord
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $clinicOwnerIdentityId,
        public string $email,
        public string $name,
        public string $authorityStatus,
        public DateTimeImmutable $establishedAt,
        public ?DateTimeImmutable $revokedAt,
        public ?string $passwordHash = null,
        public string $emailVerificationStatus = 'unverified',
        public ?DateTimeImmutable $emailVerifiedAt = null,
        public int $failedAttemptCount = 0,
        public ?DateTimeImmutable $lockoutUntil = null,
        public int $credentialVersion = 0,
    ) {}
}
