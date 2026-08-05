<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Assets;

use App\Modules\WebsiteBuilder\Application\WebsiteAsset\PublicWebsiteAssetFile;

interface PublicWebsiteAssetReadInterface
{
    public function available(string $assetId): ?PublicWebsiteAssetFile;
}
