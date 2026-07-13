<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\WorkforceCredentials;

use DateTimeImmutable;
use SensitiveParameter;

interface CredentialVerificationInterface
{
    public function verify(
        string $email,
        #[SensitiveParameter] string $plainPassword,
        DateTimeImmutable $verifiedAt,
    ): CredentialVerificationResult;
}
