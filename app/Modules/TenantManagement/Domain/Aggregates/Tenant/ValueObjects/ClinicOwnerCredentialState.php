<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Contracts\ClinicOwnerPasswordMatcherInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityStateException;
use DateTimeImmutable;

final readonly class ClinicOwnerCredentialState
{
    public function __construct(
        private ?string $passwordHash = null,
        public ?DateTimeImmutable $emailVerifiedAt = null,
        public int $failedAttemptCount = 0,
        public ?DateTimeImmutable $lockoutUntil = null,
        public int $credentialVersion = 0,
    ) {
        if ($passwordHash !== null && trim($passwordHash) === '') {
            throw new InvalidClinicOwnerAuthorityStateException('A Clinic Owner password hash cannot be empty.');
        }

        if ($failedAttemptCount < 0 || $failedAttemptCount > 5) {
            throw new InvalidClinicOwnerAuthorityStateException('Clinic Owner failed attempts must be between zero and five.');
        }

        if (($failedAttemptCount === 5) !== ($lockoutUntil !== null)) {
            throw new InvalidClinicOwnerAuthorityStateException('Clinic Owner lockout state is inconsistent.');
        }

        if ($credentialVersion < 0 || ($passwordHash === null && $credentialVersion !== 0)) {
            throw new InvalidClinicOwnerAuthorityStateException('Clinic Owner credential version is inconsistent.');
        }
    }

    public static function uninitialized(): self
    {
        return new self;
    }

    public function isUsable(): bool
    {
        return $this->passwordHash !== null;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function isLockedAt(DateTimeImmutable $instant): bool
    {
        return $this->lockoutUntil !== null && $this->lockoutUntil > $instant;
    }

    public function matches(ClinicOwnerPasswordMatcherInterface $matcher): bool
    {
        return $this->passwordHash !== null && $matcher->matches($this->passwordHash);
    }

    public function recordFailure(DateTimeImmutable $occurredAt): self
    {
        $failedAttempts = $this->lockoutUntil === null ? $this->failedAttemptCount + 1 : 1;
        $failedAttempts = min($failedAttempts, 5);

        return new self(
            $this->passwordHash,
            $this->emailVerifiedAt,
            $failedAttempts,
            $failedAttempts === 5 ? $occurredAt->modify('+15 minutes') : null,
            $this->credentialVersion,
        );
    }

    public function recordSuccess(): self
    {
        return new self(
            $this->passwordHash,
            $this->emailVerifiedAt,
            0,
            null,
            $this->credentialVersion,
        );
    }

    public function verifyEmail(DateTimeImmutable $verifiedAt): self
    {
        if ($this->isEmailVerified()) {
            throw new InvalidClinicOwnerAuthorityStateException('Clinic Owner email is already verified.');
        }

        return new self(
            $this->passwordHash,
            $verifiedAt,
            $this->failedAttemptCount,
            $this->lockoutUntil,
            $this->credentialVersion,
        );
    }

    public function changePasswordHash(string $passwordHash): self
    {
        return new self(
            $passwordHash,
            $this->emailVerifiedAt,
            0,
            null,
            $this->credentialVersion + 1,
        );
    }

    /** @internal Persistence mapping only. */
    public function passwordHashForPersistence(): ?string
    {
        return $this->passwordHash;
    }
}
