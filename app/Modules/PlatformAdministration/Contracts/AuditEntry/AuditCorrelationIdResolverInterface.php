<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\AuditEntry;

interface AuditCorrelationIdResolverInterface
{
    public function resolve(): string;
}
