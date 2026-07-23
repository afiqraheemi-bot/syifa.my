<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

use DateTimeImmutable;

interface PlatformSessionStoreInterface
{
    public function establish(PlatformPrincipal $principal, DateTimeImmutable $authenticatedAt, bool $remember = false): PlatformSessionState;

    public function current(DateTimeImmutable $at): ?PlatformSessionState;

    public function updateLastActivity(DateTimeImmutable $lastActivityAt): void;

    public function invalidate(): void;
}
