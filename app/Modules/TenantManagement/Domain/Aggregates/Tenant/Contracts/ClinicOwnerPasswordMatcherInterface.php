<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\Contracts;

interface ClinicOwnerPasswordMatcherInterface
{
    public function matches(string $passwordHash): bool;
}
