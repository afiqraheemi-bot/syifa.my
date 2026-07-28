<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\ServiceSetup;

interface ServiceSetupAuditInterface
{
    /** @param array<string, scalar|null> $metadata */
    public function record(
        string $tenantId,
        string $actorId,
        string $correlationId,
        string $action,
        string $serviceId,
        array $metadata,
    ): void;
}
