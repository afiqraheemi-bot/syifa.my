<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Contracts\ClinicOwnerPasswordMatcherInterface;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

final class ClinicOwnerPasswordMatcher implements ClinicOwnerPasswordMatcherInterface
{
    private bool $used = false;

    public function __construct(
        private readonly Hasher $hasher,
        #[SensitiveParameter] private readonly string $plainPassword,
    ) {}

    public function matches(string $passwordHash): bool
    {
        $this->used = true;

        return $this->hasher->check($this->plainPassword, $passwordHash);
    }

    public function wasUsed(): bool
    {
        return $this->used;
    }
}
