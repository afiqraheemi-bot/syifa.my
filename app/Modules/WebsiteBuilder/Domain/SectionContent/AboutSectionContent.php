<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class AboutSectionContent implements WebsiteSectionContentInterface
{
    public function __construct(private SectionId $sectionId, public ?string $heading = null, public ?string $description = null, public ?AssetId $imageReference = null)
    {
        SectionContentRules::optionalText($heading, 160, 'About heading');
        SectionContentRules::optionalText($description, 5000, 'About description');
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
