<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects;

enum CategoryGrantStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
