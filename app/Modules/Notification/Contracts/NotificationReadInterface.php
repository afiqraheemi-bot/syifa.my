<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

interface NotificationReadInterface
{
    /** @return array{entries: list<array<string, mixed>>} */
    public function forTenant(string $tenantId, ?string $status, ?string $triggerType): array;

    /** @return array{entries: list<array<string, mixed>>} */
    public function forPlatform(?string $tenantId, ?string $status, ?string $triggerType): array;
}
