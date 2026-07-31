<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

use DateTimeImmutable;

interface PlatformMfaChallengeInterface
{
    public function begin(
        PlatformPrincipal $principal,
        string $normalizedEmail,
        bool $remember,
        DateTimeImmutable $at,
    ): PlatformMfaChallengeData;

    public function complete(string $code, DateTimeImmutable $at): ?PlatformPrincipal;
}
