<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\PlatformIdentity\Events;

use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityRole;
use DateTimeImmutable;

final readonly class PlatformIdentityRoleChanged
{
    public function __construct(
        public string $platformIdentityId,
        public PlatformIdentityRole $previousRole,
        public PlatformIdentityRole $currentRole,
        public DateTimeImmutable $occurredAt,
    ) {}
}
