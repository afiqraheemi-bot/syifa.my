<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

use DateTimeImmutable;

final readonly class PendingPlatformAuthenticationData
{
    public function __construct(
        public PlatformPrincipal $principal,
        public bool $remember,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public ?string $encryptedEnrollmentSecret,
    ) {}
}
