<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteAsset;

final readonly class PublicWebsiteAssetFile
{
    public function __construct(
        public string $contents,
        public string $mimeType,
        public string $checksum,
    ) {}
}
