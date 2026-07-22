<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class FaqSectionContent implements WebsiteSectionContentInterface
{
    /** @param list<FaqEntry> $entries */
    public function __construct(private SectionId $sectionId, public array $entries = [])
    {
        SectionContentRules::uniqueItemIds($entries, 'FAQ entries');
    }

    public function isRenderable(): bool
    {
        return $this->entries !== [];
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::Faq;
    }
}
