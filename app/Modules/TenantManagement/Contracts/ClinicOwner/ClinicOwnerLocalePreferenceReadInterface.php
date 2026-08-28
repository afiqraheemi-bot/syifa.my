<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\ClinicOwner;

interface ClinicOwnerLocalePreferenceReadInterface
{
    /** @return 'en'|'ms'|null null when the owner has never explicitly chosen a language. */
    public function forTenant(string $tenantId): ?string;
}
