<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\SectionContent\WebsiteSectionContentInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;

final readonly class WebsiteDraftContent
{
    /** @var list<WebsiteSectionContentInterface> */
    public array $sections;

    /** @param list<WebsiteSectionContentInterface> $sections */
    public function __construct(
        public WebsiteId $websiteId,
        public TenantId $tenantId,
        public int $version,
        array $sections,
    ) {
        $this->sections = $sections;

        if ($version < 1) {
            throw new InvalidWebsiteValueException('Website Draft version must be positive.');
        }

        if (count($this->sections) !== count(SectionType::cases())) {
            throw new InvalidWebsiteValueException('Website Draft must contain every governed Section exactly once.');
        }
        $sectionIds = [];
        foreach (SectionType::cases() as $index => $expectedType) {
            $section = $this->sections[$index];
            if ($section->sectionType() !== $expectedType) {
                throw new InvalidWebsiteValueException('Website Draft Sections must follow governed order.');
            }
            if (isset($sectionIds[$section->sectionId()->value])) {
                throw new InvalidWebsiteValueException('Website Draft contains duplicate Section identifiers.');
            }
            $sectionIds[$section->sectionId()->value] = true;
        }
    }
}
