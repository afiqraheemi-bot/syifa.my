<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;

final readonly class PublishedAssetSnapshot
{
    public function __construct(public AssetId $assetId, public string $storageKey, public AssetMimeType $mimeType, public int $fileSizeBytes, public ?int $width, public ?int $height, public string $checksum) {}
}
