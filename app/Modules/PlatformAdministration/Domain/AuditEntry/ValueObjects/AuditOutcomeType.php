<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects;

enum AuditOutcomeType: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Denied = 'denied';
}
