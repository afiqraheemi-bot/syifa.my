<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\AuditEntry;

final readonly class AuditOutcomeData
{
    public function __construct(
        public string $outcome,
        public ?string $reasonCode,
    ) {}
}
