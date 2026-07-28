<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Administration;

interface ClinicOwnerSetupTokenVerifierInterface
{
    public function resolve(string $email, string $token): ?ClinicOwnerSetupCredentialData;

    public function consume(ClinicOwnerSetupCredentialData $credential): void;
}
