<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Administration;

use App\Modules\TenantManagement\Contracts\Administration\EstablishClinicOwnerCommand;
use App\Modules\TenantManagement\Contracts\Administration\EstablishedClinicOwnerData;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityTransitionException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

final readonly class ActivateSelfRegisteredClinicOwnerService
{
    public function __construct(
        private EstablishClinicOwnerService $owners,
        private TenantRepositoryInterface $tenants,
        private Hasher $hasher,
    ) {}

    public function execute(
        EstablishClinicOwnerCommand $command,
        #[SensitiveParameter] string $password,
    ): EstablishedClinicOwnerData {
        $owner = $this->owners->executeForSelfRegistration($command);
        $tenant = $this->tenants->find(new TenantId($owner->tenantId));
        if ($tenant === null) {
            throw new InvalidClinicOwnerAuthorityTransitionException('Clinic Owner authority is unavailable.');
        }

        $authorityId = new ClinicOwnerAuthorityId($owner->authorityId);
        $state = $tenant->findClinicOwnerAuthority($authorityId)?->credentialState();
        if ($state === null) {
            throw new InvalidClinicOwnerAuthorityTransitionException('Clinic Owner authority is unavailable.');
        }
        if (! $state->isEmailVerified()) {
            $tenant->verifyClinicOwnerEmail($authorityId, new DateTimeImmutable);
        }
        $tenant->changeClinicOwnerPasswordHash($authorityId, $this->hasher->make($password));
        $this->tenants->save($tenant);

        return $owner;
    }
}
