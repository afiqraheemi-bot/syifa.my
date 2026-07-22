<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

interface PublicAssetUrlResolverInterface
{
    public function resolve(string $assetId, PublicAssetPurpose $purpose): PublicUrl;
}
