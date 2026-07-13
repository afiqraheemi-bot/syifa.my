<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Infrastructure\TenantContext;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;

final class RecordingTenantRepository implements TenantRepositoryInterface
{
    /** @var array<string, Tenant> */
    private array $tenants = [];

    public int $findCount = 0;

    public function add(Tenant $tenant): void
    {
        $this->tenants[$tenant->id->value] = $tenant;
    }

    public function find(TenantId $tenantId): ?Tenant
    {
        $this->findCount++;

        return $this->tenants[$tenantId->value] ?? null;
    }

    public function save(Tenant $tenant): void
    {
        $this->tenants[$tenant->id->value] = $tenant;
    }
}
