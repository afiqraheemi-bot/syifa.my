<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Application\Authentication\Exceptions\InvalidClinicOwnerPasswordException;
use App\Modules\TenantManagement\Contracts\Authentication\PasswordBlocklistInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityTransitionException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

final readonly class ActivateClinicOwnerAccessService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private Hasher $hasher,
        private ClinicOwnerPasswordPolicy $passwordPolicy,
        private PasswordBlocklistInterface $passwordBlocklist,
    ) {}

    public function execute(
        string $tenantId,
        string $authorityId,
        #[SensitiveParameter] string $password,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->passwordPolicy->validate($password);
        if ($this->passwordBlocklist->contains($password)) {
            throw new InvalidClinicOwnerPasswordException('The submitted Clinic Owner password cannot be used.');
        }

        $tenant = $this->tenants->find(new TenantId($tenantId));
        if ($tenant === null) {
            throw new InvalidClinicOwnerAuthorityTransitionException('Clinic Owner setup authority is unavailable.');
        }
        $authority = new ClinicOwnerAuthorityId($authorityId);
        $state = $tenant->findClinicOwnerAuthority($authority)?->credentialState();
        if ($state === null) {
            throw new InvalidClinicOwnerAuthorityTransitionException('Clinic Owner setup authority is unavailable.');
        }
        if (! $state->isEmailVerified()) {
            $tenant->verifyClinicOwnerEmail($authority, $occurredAt);
        }
        $tenant->changeClinicOwnerPasswordHash($authority, $this->hasher->make($password));
        $this->tenants->save($tenant);
    }
}
