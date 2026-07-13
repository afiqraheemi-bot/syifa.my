<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;

final class InMemoryTenantRepository implements TenantRepositoryInterface
{
    /** @var array<string, Tenant> */
    private array $tenants = [];

    public int $saveCount = 0;

    public function add(Tenant $tenant): void
    {
        $this->tenants[$tenant->id->value] = $tenant;
    }

    public function find(TenantId $tenantId): ?Tenant
    {
        return $this->tenants[$tenantId->value] ?? null;
    }

    public function save(Tenant $tenant): void
    {
        $tenant->synchronizePersistenceVersion($tenant->version() + 1);
        $this->tenants[$tenant->id->value] = $tenant;
        $this->saveCount++;
    }
}
