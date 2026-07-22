<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

enum ServiceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
