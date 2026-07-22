<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Results;

use DateTimeImmutable;

final readonly class BookingSubmissionResult
{
    public function __construct(
        public string $bookingId,
        public string $reference,
        public string $status,
        public DateTimeImmutable $createdAt,
    ) {}
}
