<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;

interface AuthenticationSignalDispatcherInterface
{
    public function dispatch(ClinicOwnerAuthenticationSucceeded|ClinicOwnerAuthenticationRejected $signal): void;
}
