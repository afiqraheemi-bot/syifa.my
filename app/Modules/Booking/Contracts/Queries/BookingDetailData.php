<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

final readonly class BookingDetailData
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $serviceId,
        public string $reference,
        public string $status,
        public string $localDate,
        public string $localStart,
        public string $localEnd,
        public string $timezone,
        public string $startsAtUtc,
        public string $endsAtUtc,
        public int $durationMinutes,
    ) {}
}
