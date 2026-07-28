<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

final readonly class BookingDetailData
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public ?string $serviceId,
        public ?string $serviceName,
        public string $reference,
        public string $source,
        public string $status,
        public string $patientName,
        public string $patientPhone,
        public ?string $patientEmail,
        public ?string $notes,
        public string $localDate,
        public string $localStart,
        public string $localEnd,
        public string $timezone,
        public string $startsAtUtc,
        public string $endsAtUtc,
        public int $durationMinutes,
        public string $createdAt,
    ) {}
}
