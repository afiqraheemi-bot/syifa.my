<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class HeroSectionContent implements WebsiteSectionContentInterface
{
    public function __construct(
        private SectionId $sectionId,
        public ?string $headline = null,
        public ?string $subheadline = null,
        public ?string $primaryCtaLabel = null,
        public ?string $primaryCtaTarget = null,
        public ?string $secondaryCtaLabel = null,
        public ?string $secondaryCtaTarget = null,
        public ?AssetId $heroImageReference = null,
    ) {
        SectionContentRules::optionalText($headline, 160, 'Hero headline');
        SectionContentRules::optionalText($subheadline, 500, 'Hero subheadline');
        SectionContentRules::optionalText($primaryCtaLabel, 80, 'Hero primary CTA label');
        SectionContentRules::optionalTarget($primaryCtaTarget, 'Hero primary CTA target');
        SectionContentRules::paired($primaryCtaLabel, $primaryCtaTarget, 'Hero primary CTA');
        SectionContentRules::optionalText($secondaryCtaLabel, 80, 'Hero secondary CTA label');
        SectionContentRules::optionalTarget($secondaryCtaTarget, 'Hero secondary CTA target');
        SectionContentRules::paired($secondaryCtaLabel, $secondaryCtaTarget, 'Hero secondary CTA');
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::Hero;
    }

    public function isRenderable(): bool
    {
        return $this->headline !== null;
    }
}
