<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Queries;

use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantNotFoundException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantResolverInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresWebsiteTenantResolver implements WebsiteTenantResolverInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forTrustedWebsite(string $trustedWebsiteId): string
    {
        $row = $this->connection->table('websites')->where('id', $trustedWebsiteId)->first(['tenant_id']);
        $tenantId = $row->tenant_id ?? null;
        if (! is_string($tenantId) || $tenantId === '') {
            throw new WebsiteTenantNotFoundException('No Tenant is associated with the trusted Website identity.');
        }

        return $tenantId;
    }
}
