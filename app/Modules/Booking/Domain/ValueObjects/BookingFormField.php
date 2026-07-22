<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

enum BookingFormField: string
{
    // Core fields — always available, never disableable.
    case PatientName = 'patient_name';
    case Phone = 'phone';
    case AppointmentDate = 'appointment_date';
    case AppointmentTime = 'appointment_time';

    // Optional fields — individually enabled or disabled by configuration.
    case Service = 'service';
    case Doctor = 'doctor';
    case Email = 'email';
    case Branch = 'branch';
    case Notes = 'notes';
}
