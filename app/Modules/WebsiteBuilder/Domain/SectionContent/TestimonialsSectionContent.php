<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class TestimonialsSectionContent implements WebsiteSectionContentInterface
{
    /** @param list<ManualTestimonial> $testimonials */
    public function __construct(private SectionId $sectionId, public array $testimonials = [])
    {
        SectionContentRules::uniqueItemIds($testimonials, 'Manual testimonials');
    }

    public function isRenderable(): bool
    {
        return array_any($this->testimonials, static fn (ManualTestimonial $testimonial): bool => $testimonial->featured);
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::Testimonials;
    }
}
