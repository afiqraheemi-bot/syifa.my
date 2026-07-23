<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

use DateTimeImmutable;

/**
 * The canonical Public Booking Contract success response (ADR-027). Deliberately
 * narrower than the internal Booking Application `BookingSubmissionResult` — this
 * type has no `bookingId` property, structurally, per ADR-027/ADR-029.
 */
final readonly class PublicBookingSubmissionResult
{
    public function __construct(
        public string $reference,
        public string $status,
        public DateTimeImmutable $submittedAt,
    ) {}
}
