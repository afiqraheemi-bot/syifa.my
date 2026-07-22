<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;

final readonly class GalleryImage implements WebsiteSectionContentItemInterface
{
    public function __construct(public string $id, public AssetId $imageReference)
    {
        SectionContentRules::uuid($id, 'Gallery image ID');
    }

    public function identity(): string
    {
        return $this->id;
    }
}
