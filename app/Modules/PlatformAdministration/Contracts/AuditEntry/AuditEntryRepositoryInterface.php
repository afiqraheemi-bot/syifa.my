<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\AuditEntry;

use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;

interface AuditEntryRepositoryInterface
{
    public function append(AuditEntry $auditEntry): AuditEntry;
}
