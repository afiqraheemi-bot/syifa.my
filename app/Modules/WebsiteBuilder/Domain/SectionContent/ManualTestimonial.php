<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

final readonly class ManualTestimonial implements WebsiteSectionContentItemInterface
{
    public function __construct(public string $id, public string $quote, public string $authorName, public bool $featured = false)
    {
        SectionContentRules::uuid($id, 'Manual testimonial ID');
        SectionContentRules::requiredText($quote, 2000, 'Manual testimonial quote');
        SectionContentRules::requiredText($authorName, 160, 'Manual testimonial author');
    }

    public function identity(): string
    {
        return $this->id;
    }
}
