<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\Entities;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityStateException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityStatus;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerCredentialState;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;

final readonly class ClinicOwnerAuthority
{
    private ClinicOwnerCredentialState $credentialState;

    public function __construct(
        public ClinicOwnerAuthorityId $id,
        public TenantId $tenantId,
        public ClinicOwnerIdentity $clinicOwnerIdentity,
        public ClinicOwnerAuthorityStatus $status,
        public DateTimeImmutable $establishedAt,
        public ?DateTimeImmutable $revokedAt = null,
        ?ClinicOwnerCredentialState $credentialState = null,
    ) {
        $this->credentialState = $credentialState ?? ClinicOwnerCredentialState::uninitialized();
        if ($status === ClinicOwnerAuthorityStatus::Active && $revokedAt !== null) {
            throw new InvalidClinicOwnerAuthorityStateException(
                'An active Clinic Owner Authority cannot have a revocation time.',
            );
        }

        if ($status === ClinicOwnerAuthorityStatus::Revoked && $revokedAt === null) {
            throw new InvalidClinicOwnerAuthorityStateException(
                'A revoked Clinic Owner Authority requires a revocation time.',
            );
        }
    }

    public function credentialState(): ClinicOwnerCredentialState
    {
        return $this->credentialState;
    }

    public function withCredentialState(ClinicOwnerCredentialState $credentialState): self
    {
        return new self(
            $this->id,
            $this->tenantId,
            $this->clinicOwnerIdentity,
            $this->status,
            $this->establishedAt,
            $this->revokedAt,
            $credentialState,
        );
    }

    public function isActive(): bool
    {
        return $this->status === ClinicOwnerAuthorityStatus::Active;
    }

    public function belongsTo(TenantId $tenantId): bool
    {
        return $this->tenantId->value === $tenantId->value;
    }
}
