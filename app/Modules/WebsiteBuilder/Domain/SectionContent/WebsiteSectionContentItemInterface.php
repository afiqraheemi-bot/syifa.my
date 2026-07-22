<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

interface WebsiteSectionContentItemInterface
{
    public function identity(): string;
}
