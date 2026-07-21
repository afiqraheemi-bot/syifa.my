<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

enum BookingStatus: string
{
    case Submitted = 'submitted';
}
