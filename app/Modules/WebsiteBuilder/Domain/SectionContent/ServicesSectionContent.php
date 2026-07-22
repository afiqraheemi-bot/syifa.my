<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class ServicesSectionContent implements WebsiteSectionContentInterface
{
    /** @param list<string> $serviceReferences */
    public function __construct(private SectionId $sectionId, public array $serviceReferences = [])
    {
        SectionContentRules::uniqueUuids($serviceReferences, 'Service references');
    }

    /** @param list<string> $activeServiceReferences */
    public function isRenderable(array $activeServiceReferences): bool
    {
        SectionContentRules::uniqueUuids($activeServiceReferences, 'Active Service references');

        return array_intersect($this->serviceReferences, $activeServiceReferences) !== [];
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::Services;
    }
}
