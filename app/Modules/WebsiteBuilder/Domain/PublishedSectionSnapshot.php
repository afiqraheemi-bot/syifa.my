<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class PublishedSectionSnapshot
{
    public function __construct(public SectionId $sectionId, public SectionType $type, public int $displayOrder, public bool $enabled)
    {
        if ($displayOrder < 1) {
            throw new InvalidWebsiteValueException('Published Section display order is invalid.');
        }
    }
}
