<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

final readonly class AvailableSlotData
{
    public function __construct(
        public string $localStart,
        public string $localEnd,
        public string $timezone,
        public bool $available,
    ) {}
}
