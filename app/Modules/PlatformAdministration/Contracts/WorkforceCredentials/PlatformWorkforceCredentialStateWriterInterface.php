<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\WorkforceCredentials;

use DateTimeImmutable;

interface PlatformWorkforceCredentialStateWriterInterface
{
    /** Optimistic-locked on `$credential->version`; throws on a stale write. */
    public function saveState(
        PlatformWorkforceCredentialData $credential,
        DateTimeImmutable $updatedAt,
    ): PlatformWorkforceCredentialData;
}
