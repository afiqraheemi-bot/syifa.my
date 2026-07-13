<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\TenantContext;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\TenantContext\Exceptions\InvalidTenantContextStateException;
use App\Modules\TenantManagement\Domain\TenantContext\ValueObjects\PlatformIdentityId;
use App\Modules\TenantManagement\Domain\TenantContext\ValueObjects\TenantContextAssignment;
use App\Modules\TenantManagement\Domain\TenantContext\ValueObjects\TenantContextRole;

final readonly class TenantContext
{
    public function __construct(
        public ?PlatformIdentityId $platformIdentityId,
        public TenantId $tenantId,
        public TenantContextRole $role,
        public ?TenantContextAssignment $assignment,
    ) {
        if ($role === TenantContextRole::WebsiteDesigner) {
            $this->guardWebsiteDesignerContext();

            return;
        }

        if ($assignment !== null) {
            throw new InvalidTenantContextStateException(
                'Only a Website Designer Tenant Context may carry an assignment.',
            );
        }

        if ($role === TenantContextRole::SuperAdmin && $platformIdentityId === null) {
            throw new InvalidTenantContextStateException(
                'A Super Admin Tenant Context requires a Platform Identity identifier.',
            );
        }

        if (($role === TenantContextRole::PublicVisitor || $role === TenantContextRole::ClinicOwner)
            && $platformIdentityId !== null) {
            throw new InvalidTenantContextStateException(
                'Public Visitor and Clinic Owner Tenant Contexts cannot carry a Platform Identity identifier.',
            );
        }
    }

    public function isFor(TenantId $tenantId): bool
    {
        return $this->tenantId->value === $tenantId->value;
    }

    private function guardWebsiteDesignerContext(): void
    {
        if ($this->platformIdentityId === null || $this->assignment === null) {
            throw new InvalidTenantContextStateException(
                'A Website Designer Tenant Context requires a Platform Identity and active assignment.',
            );
        }

        if ($this->assignment->platformIdentityId->value !== $this->platformIdentityId->value
            || $this->assignment->tenantId->value !== $this->tenantId->value) {
            throw new InvalidTenantContextStateException(
                'Website Designer assignment identifiers must match the Tenant Context.',
            );
        }
    }
}
