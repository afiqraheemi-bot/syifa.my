<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Persistence\Lookups;

use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingData;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantAdminRoutingLabelException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantAdminRoutingLabel;
use App\Modules\TenantManagement\Infrastructure\Persistence\Exceptions\InvalidTenantStorageStateException;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresTenantAdminRoutingLookup implements TenantAdminRoutingLookupInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function resolve(string $normalizedRoutingLabel): ?TenantAdminRoutingData
    {
        try {
            $routingLabel = new TenantAdminRoutingLabel($normalizedRoutingLabel);
        } catch (InvalidTenantAdminRoutingLabelException) {
            return null;
        }

        $row = $this->connection
            ->table('tenants')
            ->where('admin_routing_label', $routingLabel->value)
            ->whereIn('status', ['active', 'reactivated'])
            ->first(['id', 'admin_routing_label', 'status']);

        if ($row === null) {
            return null;
        }

        $tenantId = $row->id ?? null;
        $storedRoutingLabel = $row->admin_routing_label ?? null;
        $lifecycleStatus = $row->status ?? null;

        if (! is_string($tenantId)
            || ! is_string($storedRoutingLabel)
            || ! is_string($lifecycleStatus)) {
            throw new InvalidTenantStorageStateException(
                'Tenant Admin Routing lookup received malformed Tenant storage.',
            );
        }

        return new TenantAdminRoutingData($tenantId, $storedRoutingLabel, $lifecycleStatus);
    }
}
