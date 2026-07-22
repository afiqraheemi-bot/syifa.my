<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

enum BookingActorType: string
{
    case PublicVisitor = 'public_visitor';
    case ClinicOwner = 'clinic_owner';
    case SuperAdmin = 'super_admin';
}
