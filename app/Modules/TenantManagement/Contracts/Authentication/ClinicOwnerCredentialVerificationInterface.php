<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

use DateTimeImmutable;
use SensitiveParameter;

interface ClinicOwnerCredentialVerificationInterface
{
    public function verify(
        string $trustedTenantId,
        string $normalizedEmail,
        #[SensitiveParameter] string $plainPassword,
        DateTimeImmutable $verifiedAt,
    ): ClinicOwnerCredentialVerificationResult;
}
