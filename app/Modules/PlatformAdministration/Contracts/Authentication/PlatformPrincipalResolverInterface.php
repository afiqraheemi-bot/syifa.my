<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

use DateTimeImmutable;

interface PlatformPrincipalResolverInterface
{
    public function resolve(DateTimeImmutable $resolvedAt): ?PlatformPrincipal;
}
