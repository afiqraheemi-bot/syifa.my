<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

interface WebsiteSeoSummaryReadInterface
{
    public function summary(string $websiteId): ?WebsiteSeoSummaryData;
}
