<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\CustomDomain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use DateTimeImmutable;

final class CustomDomain
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $websiteId,
        public readonly string $hostname,
        public readonly string $verificationTokenHash,
        private CustomDomainStatus $status,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $verifiedAt = null,
        private ?DateTimeImmutable $activatedAt = null,
        private ?DateTimeImmutable $detachedAt = null,
        private int $version = 0,
    ) {
        foreach ([$id, $tenantId, $websiteId] as $identifier) {
            if (preg_match('/^[0-9a-f-]{36}$/i', $identifier) !== 1) {
                throw new InvalidWebsiteValueException('Custom Domain identifier is invalid.');
            }
        }
        if (self::normalizeHostname($hostname) !== $hostname) {
            throw new InvalidWebsiteValueException('Custom Domain hostname must be normalized.');
        }
        if (preg_match('/^[0-9a-f]{64}$/', $verificationTokenHash) !== 1 || $version < 0) {
            throw new InvalidWebsiteValueException('Custom Domain persisted state is invalid.');
        }
    }

    public static function request(
        string $id,
        string $tenantId,
        string $websiteId,
        string $hostname,
        string $verificationTokenHash,
        DateTimeImmutable $at,
    ): self {
        return new self(
            $id,
            $tenantId,
            $websiteId,
            self::normalizeHostname($hostname),
            $verificationTokenHash,
            CustomDomainStatus::VerificationPending,
            $at,
            $at,
        );
    }

    public static function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(rtrim(trim($hostname), '.'));
        if (preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $hostname) !== 1
            || str_ends_with($hostname, '.syifa.my')) {
            throw new InvalidWebsiteValueException('Custom Domain hostname is invalid.');
        }

        return $hostname;
    }

    public function markVerified(DateTimeImmutable $at): void
    {
        if ($this->status !== CustomDomainStatus::VerificationPending) {
            throw new InvalidWebsiteValueException('Only a pending Custom Domain can be verified.');
        }
        $this->status = CustomDomainStatus::Verified;
        $this->verifiedAt = $at;
        $this->updatedAt = $at;
    }

    public function activate(DateTimeImmutable $at): void
    {
        if ($this->status !== CustomDomainStatus::Verified) {
            throw new InvalidWebsiteValueException('Only a verified Custom Domain can be activated.');
        }
        $this->status = CustomDomainStatus::Active;
        $this->activatedAt = $at;
        $this->updatedAt = $at;
    }

    public function detach(DateTimeImmutable $at): void
    {
        if (! in_array($this->status, [CustomDomainStatus::Verified, CustomDomainStatus::Active, CustomDomainStatus::Failing], true)) {
            throw new InvalidWebsiteValueException('Custom Domain cannot be detached from its current state.');
        }
        $this->status = CustomDomainStatus::Detached;
        $this->detachedAt = $at;
        $this->updatedAt = $at;
    }

    public function status(): CustomDomainStatus
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version < 1) {
            throw new InvalidWebsiteValueException('Custom Domain version is invalid.');
        }
        $this->version = $version;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function verifiedAt(): ?DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function activatedAt(): ?DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function detachedAt(): ?DateTimeImmutable
    {
        return $this->detachedAt;
    }
}
