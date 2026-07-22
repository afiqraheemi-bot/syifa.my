<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Commands;

use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

final readonly class RescheduleBookingCommand
{
    public function __construct(
        public TenantId $tenantId,
        public BookingId $bookingId,
        public string $localDate,
        public string $localStart,
        public string $actorId,
        public string $actorRole,
        public ?string $note = null,
    ) {}
}
