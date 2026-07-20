<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\AuditEntry;

final readonly class AuditActorData
{
    public function __construct(
        public string $type,
        public ?string $identityId,
    ) {}
}
