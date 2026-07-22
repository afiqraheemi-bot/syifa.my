<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Commands;

use App\Modules\Booking\Domain\ValueObjects\BookingActorType;
use App\Modules\Booking\Domain\ValueObjects\BookingSource;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

final readonly class CreateBookingCommand
{
    public function __construct(
        public TenantId $tenantId,
        public BookingSource $source,
        public BookingActorType $actorType,
        public ?string $actorId,
        public string $patientName,
        public string $phone,
        public string $appointmentDate,
        public string $appointmentTime,
        public ?string $serviceId,
        public ?string $email,
        public ?string $notes,
    ) {}
}
