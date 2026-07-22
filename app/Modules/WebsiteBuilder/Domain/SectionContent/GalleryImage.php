<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

final readonly class GalleryImage implements WebsiteSectionContentItemInterface
{
    public function __construct(public string $id, public string $imageReference)
    {
        SectionContentRules::uuid($id, 'Gallery image ID');
        SectionContentRules::uuid($imageReference, 'Gallery image reference');
    }

    public function identity(): string
    {
        return $this->id;
    }
}
