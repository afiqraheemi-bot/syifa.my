<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class BookingStorageRecord
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $clinicId,
        public string $bookingReference,
        public string $status,
        public string $patientName,
        public string $patientPhone,
        public ?string $patientEmail,
        public string $appointmentOn,
        public string $appointmentTime,
        public ?string $notes,
        public DateTimeImmutable $domainCreatedAt,
        public DateTimeImmutable $domainUpdatedAt,
        public int $version,
    ) {}
}
