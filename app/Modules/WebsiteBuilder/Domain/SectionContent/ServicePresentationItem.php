<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class ServicePresentationItem implements WebsiteSectionContentItemInterface
{
    public function __construct(public string $serviceId, public int $displayOrder, public bool $isFeatured = false)
    {
        SectionContentRules::uuid($serviceId, 'Service ID');
        if ($displayOrder < 1 || $displayOrder > 100) {
            throw new InvalidWebsiteValueException('Service presentation display order must be between 1 and 100.');
        }
    }

    public function identity(): string
    {
        return $this->serviceId;
    }
}
