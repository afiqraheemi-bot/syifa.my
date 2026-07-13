<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\PlatformIdentity\Events;

use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityStatus;
use DateTimeImmutable;

final readonly class PlatformIdentityStatusChanged
{
    public function __construct(
        public string $platformIdentityId,
        public PlatformIdentityStatus $previousStatus,
        public PlatformIdentityStatus $currentStatus,
        public DateTimeImmutable $occurredAt,
    ) {}
}
