<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Commands;

use App\Modules\Booking\Domain\ValueObjects\TenantId;

final readonly class CreateManualBookingCommand
{
    public function __construct(
        public TenantId $tenantId,
        public TenantId $actorTenantId,
        public string $actorId,
        public string $actorRole,
        public string $source,
        public string $patientName,
        public string $phone,
        public string $appointmentDate,
        public string $appointmentTime,
        public string $serviceId,
        public ?string $email = null,
        public ?string $notes = null,
    ) {}
}
