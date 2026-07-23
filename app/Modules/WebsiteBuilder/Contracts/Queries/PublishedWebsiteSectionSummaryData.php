<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

final readonly class PublishedWebsiteSectionSummaryData
{
    /** @param list<string> $highlights */
    public function __construct(
        public string $type,
        public int $displayOrder,
        public bool $enabled,
        public bool $renderable,
        public int $itemCount,
        public array $highlights = [],
    ) {}
}
