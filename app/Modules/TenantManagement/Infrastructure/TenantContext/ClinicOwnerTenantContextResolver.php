<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\TenantContext;

use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantIdentityValueException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\TenantContext\ValueObjects\TenantContextRole;

final readonly class ClinicOwnerTenantContextResolver implements TenantContextResolverInterface
{
    public function __construct(private TenantRepositoryInterface $tenants) {}

    public function resolve(TenantContextResolutionData $resolution): ?TenantContextData
    {
        if ($resolution->role !== TenantContextRole::ClinicOwner->value
            || $resolution->platformIdentityId !== null
            || $resolution->assignment !== null
            || $resolution->clinicOwnerAuthorityId === null
            || $resolution->clinicOwnerIdentityId === null) {
            return null;
        }

        try {
            $tenantId = new TenantId($resolution->tenantId);
            $authorityId = new ClinicOwnerAuthorityId($resolution->clinicOwnerAuthorityId);
            $identityId = new ClinicOwnerIdentityId($resolution->clinicOwnerIdentityId);
        } catch (InvalidTenantIdentityValueException) {
            return null;
        }

        $tenant = $this->tenants->find($tenantId);

        if ($tenant === null || ! $tenant->permitsClinicOwnerAuthenticatedAccess()) {
            return null;
        }

        $authority = $tenant->findActiveClinicOwnerAuthorityByIdentity($identityId);

        if ($authority === null
            || $authority->id->value !== $authorityId->value
            || ! $authority->belongsTo($tenantId)) {
            return null;
        }

        return new TenantContextData(
            platformIdentityId: null,
            tenantId: $tenant->id->value,
            role: TenantContextRole::ClinicOwner->value,
            assignment: null,
        );
    }
}
