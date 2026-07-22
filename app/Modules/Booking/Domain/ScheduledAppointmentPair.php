<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain;

use App\Modules\Booking\Domain\ValueObjects\ScheduledAppointment;

final readonly class ScheduledAppointmentPair
{
    public function __construct(public ScheduledAppointment $old, public ScheduledAppointment $new) {}
}
