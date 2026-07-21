<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Repositories;

use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\ValueObjects\BookingId;

interface BookingRepositoryInterface
{
    public function findById(BookingId $bookingId): ?Booking;

    public function findByReference(string $reference): ?Booking;

    public function save(Booking $booking): void;
}
