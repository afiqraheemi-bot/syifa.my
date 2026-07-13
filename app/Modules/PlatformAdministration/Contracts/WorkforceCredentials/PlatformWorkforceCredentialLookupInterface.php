<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\WorkforceCredentials;

interface PlatformWorkforceCredentialLookupInterface
{
    public function findByNormalizedEmail(string $email): ?PlatformWorkforceCredentialData;
}
