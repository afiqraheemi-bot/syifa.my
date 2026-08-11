<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantOverview;

interface TenantOverviewReadInterface
{
    public function summary(): TenantOverviewSummaryData;

    /** @return list<TenantOverviewData> */
    public function list(?string $status, ?string $cursor, int $limit, ?string $search): array;
}
