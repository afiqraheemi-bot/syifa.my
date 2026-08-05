<?php

declare(strict_types=1);

namespace App\Support\PublicWebsite\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Contracts\Assets\PublicWebsiteAssetReadInterface;
use Illuminate\Http\Response;

final readonly class PublicWebsiteAssetController
{
    public function __invoke(string $assetId, PublicWebsiteAssetReadInterface $assets): Response
    {
        $asset = $assets->available($assetId);
        abort_if($asset === null, 404);

        return response($asset->contents, 200, [
            'Content-Type' => $asset->mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.$asset->checksum.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
