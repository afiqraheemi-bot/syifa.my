<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Provisioning;

use App\Modules\TenantManagement\Contracts\Provisioning\ProvisionedTenantData;
use App\Modules\TenantManagement\Contracts\Provisioning\ProvisionTenantCommand;
use App\Modules\TenantManagement\Contracts\Provisioning\ProvisionTenantInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;

final readonly class ProvisionTenantService implements ProvisionTenantInterface
{
    public function __construct(private TenantRepositoryInterface $tenants) {}

    public function execute(ProvisionTenantCommand $command): ProvisionedTenantData
    {
        $tenantId = new TenantId($command->tenantId);
        $existing = $this->tenants->find($tenantId);
        if ($existing !== null) {
            return $this->data($existing, true);
        }

        $tenant = Tenant::provision($tenantId, $command->occurredAt);
        $this->tenants->save($tenant);

        return $this->data($tenant, false);
    }

    private function data(Tenant $tenant, bool $alreadyExisted): ProvisionedTenantData
    {
        return new ProvisionedTenantData(
            $tenant->id->value,
            $tenant->status()->value,
            $tenant->version(),
            $alreadyExisted,
        );
    }
}
