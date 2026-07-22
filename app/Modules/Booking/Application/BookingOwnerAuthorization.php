<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Exceptions\BookingOperationForbiddenException;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

final class BookingOwnerAuthorization
{
    public function assertClinicOwner(string $actorId, string $role): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $actorId) !== 1 || $role !== 'clinic_owner') {
            throw new BookingOperationForbiddenException('Booking operation is not authorized.');
        }
    }

    public function assertClinicOwnerForTenant(string $actorId, string $role, TenantId $actorTenantId, TenantId $targetTenantId): void
    {
        $this->assertClinicOwner($actorId, $role);
        if ($actorTenantId->value !== $targetTenantId->value) {
            throw new BookingOperationForbiddenException('Booking operation is not authorized.');
        }
    }
}
