<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

enum BookingHistoryEventType: string
{
    case Submitted = 'BookingSubmitted';
    case Confirmed = 'BookingConfirmed';
    case Rescheduled = 'AppointmentRescheduled';
    case Cancelled = 'BookingCancelled';
}
