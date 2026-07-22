<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\Exceptions\PublicAssetResolutionException;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicAssetPurpose;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicAssetUrlResolverInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicUrl;

final readonly class OriginPublicAssetUrlResolver implements PublicAssetUrlResolverInterface
{
    public function __construct(private string $publicOrigin)
    {
        try {
            new PublicUrl($publicOrigin);
        } catch (\Throwable $exception) {
            throw new PublicAssetResolutionException('Public Asset origin is invalid.', previous: $exception);
        }
    }

    public function resolve(string $assetId, PublicAssetPurpose $purpose): PublicUrl
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $assetId) !== 1) {
            throw new PublicAssetResolutionException('Public Asset identity is invalid.');
        }

        return new PublicUrl(rtrim($this->publicOrigin, '/').'/assets/'.rawurlencode($assetId).'?purpose='.rawurlencode($purpose->value));
    }
}
