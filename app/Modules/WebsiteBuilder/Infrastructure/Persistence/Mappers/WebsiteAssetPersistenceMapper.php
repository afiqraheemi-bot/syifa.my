<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetStatus;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteAssetStorageRecord;

final class WebsiteAssetPersistenceMapper
{
    public function record(string $websiteId, WebsiteAsset $asset): WebsiteAssetStorageRecord
    {
        return new WebsiteAssetStorageRecord($asset->id->value, $websiteId, $asset->tenantId->value, $asset->storageKey, $asset->mimeType->value, $asset->fileSizeBytes, $asset->width, $asset->height, $asset->checksum, $asset->status()->value, $asset->createdAt, $asset->updatedAt(), $asset->version());
    }

    public function toDomain(WebsiteAssetStorageRecord $record): WebsiteAsset
    {
        return new WebsiteAsset(new AssetId($record->id), new TenantId($record->tenantId), $record->storageKey, AssetMimeType::fromStored($record->mimeType), $record->fileSizeBytes, $record->width, $record->height, $record->checksum, AssetStatus::fromStored($record->status), $record->domainCreatedAt, $record->domainUpdatedAt, $record->version);
    }
}
