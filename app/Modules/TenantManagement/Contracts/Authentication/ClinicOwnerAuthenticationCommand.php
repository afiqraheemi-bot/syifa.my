<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

use DateTimeImmutable;
use SensitiveParameter;

final readonly class ClinicOwnerAuthenticationCommand
{
    public function __construct(
        public string $trustedTenantSelectorReference,
        public string $email,
        #[SensitiveParameter] private string $plainPassword,
        public DateTimeImmutable $attemptedAt,
    ) {}

    public function plainPassword(): string
    {
        return $this->plainPassword;
    }
}
