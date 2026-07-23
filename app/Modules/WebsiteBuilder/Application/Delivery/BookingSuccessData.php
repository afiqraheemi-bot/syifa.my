<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use DateTimeImmutable;

/**
 * Exactly ADR-027's Success Contract fields — reference, status, timestamp.
 * No `bookingId` property exists on this type at all.
 */
final readonly class BookingSuccessData
{
    public function __construct(
        public string $reference,
        public string $status,
        public DateTimeImmutable $submittedAt,
    ) {}
}
