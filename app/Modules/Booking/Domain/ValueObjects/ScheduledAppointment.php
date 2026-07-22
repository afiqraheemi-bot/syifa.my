<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use DateTimeImmutable;

final readonly class ScheduledAppointment
{
    public function __construct(
        public AppointmentDate $localDate,
        public AppointmentTime $localStart,
        public AppointmentTime $localEnd,
        public string $timezone,
        public DateTimeImmutable $startsAtUtc,
        public DateTimeImmutable $endsAtUtc,
        public int $durationMinutes,
    ) {
        if ($timezone === '' || $endsAtUtc <= $startsAtUtc || $durationMinutes < 1) {
            throw new InvalidBookingValueException('Scheduled appointment snapshot is invalid.');
        }
        if (($endsAtUtc->getTimestamp() - $startsAtUtc->getTimestamp()) !== $durationMinutes * 60) {
            throw new InvalidBookingValueException('Scheduled appointment duration must match its UTC interval.');
        }
    }
}
