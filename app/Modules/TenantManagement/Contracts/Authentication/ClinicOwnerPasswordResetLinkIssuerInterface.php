<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

interface ClinicOwnerPasswordResetLinkIssuerInterface
{
    public function issueForEmail(string $email): void;
}
