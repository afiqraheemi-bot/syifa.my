<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class GallerySectionRenderModel implements SectionRenderContract
{
    /** @param list<GalleryImageRenderModel> $images */
    public function __construct(public array $images) {}

    public function type(): string
    {
        return 'GALLERY';
    }
}
