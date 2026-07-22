<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Repositories;

use App\Modules\Booking\Domain\BookingHistoryEntry;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

interface BookingHistoryRepositoryInterface
{
    public function append(BookingHistoryEntry $entry): void;

    /** @return list<BookingHistoryEntry> */
    public function forBooking(TenantId $tenantId, BookingId $bookingId): array;
}
