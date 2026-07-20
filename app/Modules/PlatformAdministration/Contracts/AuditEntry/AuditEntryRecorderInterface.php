<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\AuditEntry;

use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;

interface AuditEntryRecorderInterface
{
    public function record(AuditEntryData $auditEntry): AuditEntry;
}
