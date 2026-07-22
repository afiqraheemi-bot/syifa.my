<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class AboutSectionContent implements WebsiteSectionContentInterface
{
    public function __construct(private SectionId $sectionId, public ?string $heading = null, public ?string $description = null, public ?string $imageReference = null)
    {
        SectionContentRules::optionalText($heading, 160, 'About heading');
        SectionContentRules::optionalText($description, 5000, 'About description');
        SectionContentRules::optionalUuid($imageReference, 'About image reference');
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::About;
    }

    public function isRenderable(): bool
    {
        return $this->heading !== null && $this->description !== null;
    }
}
