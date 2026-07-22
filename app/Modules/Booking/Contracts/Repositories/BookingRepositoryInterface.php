<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Repositories;

use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

interface BookingRepositoryInterface
{
    public function findById(TenantId $tenantId, BookingId $bookingId): ?Booking;

    public function findByReference(TenantId $tenantId, string $reference): ?Booking;

    public function save(Booking $booking): void;
}
