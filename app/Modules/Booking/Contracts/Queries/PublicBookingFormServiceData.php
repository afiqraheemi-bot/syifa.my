<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

final readonly class PublicBookingFormServiceData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
