<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class GallerySectionContent implements WebsiteSectionContentInterface
{
    /** @param list<GalleryImage> $images */
    public function __construct(private SectionId $sectionId, public array $images = [])
    {
        SectionContentRules::uniqueItemIds($images, 'Gallery images');
        $references = array_map(static fn (GalleryImage $image): string => $image->imageReference->value, $images);
        if (count(array_unique($references)) !== count($references)) {
            throw new InvalidWebsiteValueException('Gallery contains duplicate image references.');
        }
    }

    public function isRenderable(): bool
    {
        return $this->images !== [];
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::Gallery;
    }
}
