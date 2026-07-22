<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;

final readonly class ContactSectionContent implements WebsiteSectionContentInterface
{
    public function __construct(private SectionId $sectionId) {}

    public function isRenderable(WebsiteBranding $branding): bool
    {
        return trim($branding->contactPhone) !== '' || trim($branding->contactEmail) !== '';
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::Contact;
    }
}
