<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

final readonly class WebsiteSeoSummaryData
{
    public function __construct(
        public string $metaTitle,
        public string $robotsDirective,
        public bool $indexingEnabled,
    ) {}
}
