<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class AssetRenderModel
{
    public function __construct(public string $assetId, public string $mimeType, public ?int $width, public ?int $height) {}
}
