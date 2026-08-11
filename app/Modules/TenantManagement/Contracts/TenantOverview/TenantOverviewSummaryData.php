<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantOverview;

final readonly class TenantOverviewSummaryData
{
    public function __construct(
        public int $total,
        public int $operational,
        public int $provisioning,
        public int $needsAttention,
    ) {}
}
