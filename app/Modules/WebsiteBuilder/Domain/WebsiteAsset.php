<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetAvailabilityEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetStatus;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetUsage;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use DateTimeImmutable;

final class WebsiteAsset
{
    public function __construct(
        public readonly AssetId $id,
        public readonly TenantId $tenantId,
        public readonly string $storageKey,
        public readonly AssetMimeType $mimeType,
        public readonly int $fileSizeBytes,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly string $checksum,
        private AssetStatus $status,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private int $version = 0,
    ) {
        if (trim($storageKey) === '' || mb_strlen($storageKey) > 1024 || str_starts_with($storageKey, '/') || str_contains($storageKey, '..') || preg_match('/[\x00-\x1F\x7F]/u', $storageKey) === 1) {
            throw new InvalidWebsiteValueException('Asset storage key is invalid.');
        }
        if ($fileSizeBytes < 1) {
            throw new InvalidWebsiteValueException('Asset file size must be positive.');
        }
        foreach ([$width, $height] as $dimension) {
            if ($dimension !== null && $dimension < 1) {
                throw new InvalidWebsiteValueException('Asset dimensions must be positive when provided.');
            }
        }
        if (preg_match('/^[0-9a-f]{64}$/', $checksum) !== 1) {
            throw new InvalidWebsiteValueException('Asset checksum must be a lowercase SHA-256 digest.');
        }
        if ($version < 0 || $updatedAt < $createdAt) {
            throw new InvalidWebsiteValueException('Website Asset persisted state is invalid.');
        }
    }

    public static function register(AssetId $id, TenantId $tenantId, string $storageKey, AssetMimeType $mimeType, int $fileSizeBytes, ?int $width, ?int $height, string $checksum, DateTimeImmutable $at): self
    {
        return new self($id, $tenantId, $storageKey, $mimeType, $fileSizeBytes, $width, $height, $checksum, AssetStatus::Pending, $at, $at);
    }

    public function status(): AssetStatus
    {
        return $this->status;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function markAvailable(AssetAvailabilityEvidence $evidence, DateTimeImmutable $at): void
    {
        if ($this->status !== AssetStatus::Pending) {
            throw new InvalidWebsiteValueException('Only a pending Asset can become available.');
        }
        $this->status = AssetStatus::Available;
        $this->updatedAt = $at;
    }

    public function archive(DateTimeImmutable $at): void
    {
        if ($this->status === AssetStatus::Archived) {
            return;
        }
        $this->status = AssetStatus::Archived;
        $this->updatedAt = $at;
    }

    public function isEligibleFor(AssetUsage $usage): bool
    {
        return $this->status === AssetStatus::Available && ($this->mimeType !== AssetMimeType::Svg || $usage === AssetUsage::Logo);
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version < 1) {
            throw new InvalidWebsiteValueException('Persisted Website Asset version must be positive.');
        }
        $this->version = $version;
    }
}
