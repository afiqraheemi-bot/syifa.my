<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects;

enum AuditActorType: string
{
    case Anonymous = 'anonymous';
    case PlatformIdentity = 'platform_identity';
    case ClinicOwner = 'clinic_owner';
    case System = 'system';
}
