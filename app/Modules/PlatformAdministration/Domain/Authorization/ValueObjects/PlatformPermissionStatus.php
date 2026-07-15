<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects;

enum PlatformPermissionStatus: string
{
    case Active = 'active';
    case Retired = 'retired';
}
