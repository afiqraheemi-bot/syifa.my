<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteAsset;

final readonly class UploadedWebsiteImage
{
    public function __construct(
        public string $assetId,
        public string $mimeType,
        public int $fileSizeBytes,
        public int $width,
        public int $height,
        public int $websiteVersion,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'asset_id' => $this->assetId,
            'mime_type' => $this->mimeType,
            'file_size_bytes' => $this->fileSizeBytes,
            'width' => $this->width,
            'height' => $this->height,
            'website_version' => $this->websiteVersion,
        ];
    }
}
