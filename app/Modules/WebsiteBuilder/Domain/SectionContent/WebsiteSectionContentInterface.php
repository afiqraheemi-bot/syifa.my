<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

interface WebsiteSectionContentInterface
{
    public function sectionId(): SectionId;

    public function sectionType(): SectionType;
}
