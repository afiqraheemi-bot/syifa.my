<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class BookingCtaSectionContent implements WebsiteSectionContentInterface
{
    public function __construct(private SectionId $sectionId, public ?string $heading = null, public ?string $description = null, public ?string $buttonLabel = null)
    {
        SectionContentRules::optionalText($heading, 160, 'Booking CTA heading');
        SectionContentRules::optionalText($description, 1000, 'Booking CTA description');
        SectionContentRules::optionalText($buttonLabel, 80, 'Booking CTA button label');
    }

    public function isRenderable(bool $bookingEnabled): bool
    {
        return $bookingEnabled && $this->heading !== null && $this->description !== null && $this->buttonLabel !== null;
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::BookingCta;
    }
}
