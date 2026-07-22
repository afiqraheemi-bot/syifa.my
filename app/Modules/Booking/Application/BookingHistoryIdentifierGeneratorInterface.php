<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

interface BookingHistoryIdentifierGeneratorInterface
{
    public function generate(): string;
}
