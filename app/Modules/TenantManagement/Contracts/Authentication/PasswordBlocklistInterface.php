<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

use SensitiveParameter;

interface PasswordBlocklistInterface
{
    public function contains(#[SensitiveParameter] string $password): bool;
}
