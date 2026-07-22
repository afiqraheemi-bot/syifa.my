<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Commands;

use App\Modules\Booking\Domain\ValueObjects\TenantId;

final readonly class SubmitBookingCommand
{
    public function __construct(
        public TenantId $tenantId,
        public string $patientName,
        public string $phone,
        public string $appointmentDate,
        public string $appointmentTime,
        public ?string $serviceId = null,
        public ?string $email = null,
        public ?string $notes = null,
    ) {}
}
