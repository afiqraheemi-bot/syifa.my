<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

use DateTimeImmutable;
use SensitiveParameter;

interface PlatformSessionAuthenticationInterface
{
    public function authenticate(
        string $email,
        #[SensitiveParameter] string $plainPassword,
        DateTimeImmutable $attemptedAt,
        bool $remember = false,
        bool $establishSession = true,
    ): ?PlatformPrincipal;
}
