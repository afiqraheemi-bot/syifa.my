<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;

final readonly class ClinicOwnerAuthenticationResult
{
    public function __construct(
        public ClinicOwnerAuthenticationOutcome $outcome,
        public ?ClinicOwnerAuthenticatedPrincipal $principal,
        public ?TenantContextData $tenantContext,
        public ClinicOwnerAuthenticationSucceeded|ClinicOwnerAuthenticationRejected $signal,
    ) {}
}
