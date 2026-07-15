<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects;

enum PlatformAdministratorStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
