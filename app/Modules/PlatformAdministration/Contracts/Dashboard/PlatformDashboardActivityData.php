<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Dashboard;

use DateTimeImmutable;

final readonly class PlatformDashboardActivityData
{
    public function __construct(
        public string $id,
        public string $action,
        public string $outcome,
        public DateTimeImmutable $occurredAt,
    ) {}
}
