<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use Illuminate\Support\Str;

final class BookingHistoryIdentifierGenerator implements BookingHistoryIdentifierGeneratorInterface
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
