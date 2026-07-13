<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Infrastructure\TenantRouting;

use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingData;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;

final class RecordingTenantAdminRoutingLookup implements TenantAdminRoutingLookupInterface
{
    /** @var list<string> */
    public array $resolvedLabels = [];

    public function __construct(private readonly ?TenantAdminRoutingData $result) {}

    public function resolve(string $normalizedRoutingLabel): ?TenantAdminRoutingData
    {
        $this->resolvedLabels[] = $normalizedRoutingLabel;

        return $this->result;
    }
}
