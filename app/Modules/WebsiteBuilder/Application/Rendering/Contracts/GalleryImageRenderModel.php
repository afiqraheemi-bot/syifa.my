<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class GalleryImageRenderModel
{
    public function __construct(
        public string $assetId,
        public ?string $altText,
        public ?string $caption,
        public int $displayOrder,
        public bool $decorative,
    ) {}
}
