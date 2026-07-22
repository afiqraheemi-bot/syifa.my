<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class DoctorsSectionContent implements WebsiteSectionContentInterface
{
    /** @param list<ManualDoctorProfile> $profiles */
    public function __construct(private SectionId $sectionId, public array $profiles = [])
    {
        SectionContentRules::uniqueItemIds($profiles, 'Manual doctor profiles');
    }

    public function isRenderable(): bool
    {
        return array_any($this->profiles, static fn (ManualDoctorProfile $profile): bool => $profile->visible);
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::Doctors;
    }
}
