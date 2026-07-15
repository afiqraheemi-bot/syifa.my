<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects;

enum PlatformCategoryStatus: string
{
    case Active = 'active';
    case Retired = 'retired';
}
