<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects;

final readonly class ClinicOwnerCredentialVerification
{
    public function __construct(
        public bool $verified,
        public bool $stateChanged,
        public ?string $authorityId = null,
        public ?string $identityId = null,
        public bool $emailVerified = false,
    ) {}
}
