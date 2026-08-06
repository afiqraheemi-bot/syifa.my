<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;

final class WebsiteAuthorization
{
    public function assertCanUpdate(WebsiteAuthorizationContext $context, TenantId $tenantId): void
    {
        $validActor = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $context->actorId) === 1;
        $allowed = match ($context->role) {
            'clinic_owner' => $context->actorTenantId === $tenantId->value,
            'website_designer' => $context->assignedTenantId === $tenantId->value,
            'super_admin' => $context->supportAuthorized,
            default => false,
        };
        if (! $validActor || ! $allowed) {
            throw new WebsiteOperationForbiddenException('Website operation is not authorized.');
        }
    }

    public function assertCanManageClinicBooking(WebsiteAuthorizationContext $context, TenantId $tenantId): void
    {
        $validActor = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $context->actorId) === 1;
        if (! $validActor || $context->role !== 'clinic_owner' || $context->actorTenantId !== $tenantId->value) {
            throw new WebsiteOperationForbiddenException('Clinic Booking schedule operation is not authorized.');
        }
    }
}
