<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\WorkforceCredentials;

use DateTimeImmutable;
use SensitiveParameter;

interface PlatformWorkforceCredentialPasswordWriterInterface
{
    /** A successful password write also clears any existing lockout state. */
    public function updatePassword(
        string $platformIdentityId,
        #[SensitiveParameter] string $newPlainPassword,
        DateTimeImmutable $updatedAt,
    ): void;
}
