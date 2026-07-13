<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects;

final readonly class ClinicOwnerIdentity
{
    public function __construct(
        public ClinicOwnerIdentityId $id,
        public ClinicOwnerEmail $email,
        public ClinicOwnerName $name,
    ) {}
}
