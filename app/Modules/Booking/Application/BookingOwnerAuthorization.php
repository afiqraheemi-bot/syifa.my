<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Exceptions\BookingOperationForbiddenException;

final class BookingOwnerAuthorization
{
    public function assertClinicOwner(string $actorId, string $role): void
    {
        if ($actorId === '' || $role !== 'clinic_owner') {
            throw new BookingOperationForbiddenException('Booking operation is not authorized.');
        }
    }
}
