<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

interface WebsiteReadInterface
{
    public function summary(string $trustedTenantId): ?WebsiteSummaryData;

    public function detail(string $trustedTenantId): ?WebsiteDetailData;
}
