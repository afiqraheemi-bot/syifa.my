<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;

interface TenantRepositoryInterface
{
    public function find(TenantId $tenantId): ?Tenant;

    public function save(Tenant $tenant): void;
}
