<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

use DateTimeImmutable;

final readonly class PlatformSessionState
{
    public function __construct(
        public PlatformPrincipal $principal,
        public DateTimeImmutable $authenticatedAt,
        public DateTimeImmutable $lastActivityAt,
        public DateTimeImmutable $idleExpiresAt,
        public DateTimeImmutable $absoluteExpiresAt,
    ) {}
}
