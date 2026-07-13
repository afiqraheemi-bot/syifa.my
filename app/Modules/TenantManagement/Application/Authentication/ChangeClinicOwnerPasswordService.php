<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Application\Authentication\Exceptions\InvalidClinicOwnerPasswordException;
use App\Modules\TenantManagement\Contracts\Authentication\PasswordBlocklistInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityTransitionException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

final readonly class ChangeClinicOwnerPasswordService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private Hasher $hasher,
        private ClinicOwnerPasswordPolicy $passwordPolicy,
        private PasswordBlocklistInterface $passwordBlocklist,
    ) {}

    public function execute(
        TenantId $tenantId,
        ClinicOwnerAuthorityId $authorityId,
        #[SensitiveParameter] string $plainPassword,
    ): void {
        $this->passwordPolicy->validate($plainPassword);

        if ($this->passwordBlocklist->contains($plainPassword)) {
            throw new InvalidClinicOwnerPasswordException(
                'The submitted Clinic Owner password cannot be used.',
            );
        }

        $tenant = $this->tenants->find($tenantId);

        if ($tenant === null) {
            throw new InvalidClinicOwnerAuthorityTransitionException(
                'Clinic Owner password cannot be changed for the requested Tenant authority.',
            );
        }

        $tenant->changeClinicOwnerPasswordHash($authorityId, $this->hasher->make($plainPassword));
        $this->tenants->save($tenant);
    }
}
