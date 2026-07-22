<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetUsage;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;

final class WebsiteAssetCollection
{
    /** @param list<WebsiteAsset> $assets */
    public function __construct(private array $assets = [])
    {
        $this->validate();
    }

    /** @return list<WebsiteAsset> */
    public function assets(): array
    {
        return $this->assets;
    }

    public function add(WebsiteAsset $asset, TenantId $websiteTenantId): void
    {
        if ($asset->tenantId->value !== $websiteTenantId->value) {
            throw new InvalidWebsiteValueException('Website Asset Tenant lineage does not match Website ownership.');
        }
        foreach ($this->assets as $existing) {
            if ($existing->id->value === $asset->id->value || $existing->storageKey === $asset->storageKey) {
                throw new InvalidWebsiteValueException('Website Assets require unique identity and storage key.');
            }
        }
        $this->assets[] = $asset;
    }

    public function asset(AssetId $id): WebsiteAsset
    {
        foreach ($this->assets as $asset) {
            if ($asset->id->value === $id->value) {
                return $asset;
            }
        }
        throw new InvalidWebsiteValueException('Website Asset was not found.');
    }

    public function isEligible(AssetId $id, AssetUsage $usage): bool
    {
        return $this->asset($id)->isEligibleFor($usage);
    }

    private function validate(): void
    {
        $ids = array_map(static fn (WebsiteAsset $asset): string => $asset->id->value, $this->assets);
        $keys = array_map(static fn (WebsiteAsset $asset): string => $asset->storageKey, $this->assets);
        if (count(array_unique($ids)) !== count($ids) || count(array_unique($keys)) !== count($keys)) {
            throw new InvalidWebsiteValueException('Website Assets require unique identity and storage key.');
        }
    }
}
