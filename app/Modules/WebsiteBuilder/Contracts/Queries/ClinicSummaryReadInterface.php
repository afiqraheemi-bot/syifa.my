<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

interface ClinicSummaryReadInterface
{
    public function summary(string $trustedTenantId): ?ClinicSummaryData;
}
