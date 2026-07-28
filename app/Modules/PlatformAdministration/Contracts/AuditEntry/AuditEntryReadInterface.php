<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\AuditEntry;

interface AuditEntryReadInterface
{
    /**
     * @return array{
     *   entries: list<array{
     *     id: string,
     *     occurredAt: string,
     *     actorType: string,
     *     actorIdentityId: ?string,
     *     tenantId: ?string,
     *     action: string,
     *     targetType: string,
     *     targetId: string,
     *     outcome: string,
     *     reasonCode: ?string,
     *     correlationId: string
     *   }>
     * }
     */
    public function search(
        ?string $action,
        ?string $outcome,
        ?string $actorType,
        ?string $tenantId,
        ?string $correlationId,
    ): array;
}
