<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\WorkforceCredentials;

final readonly class CredentialVerificationResult
{
    public function __construct(
        public bool $verified,
        public ?string $platformIdentityId,
    ) {}
}
