<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

final class BookingReferenceGenerator implements BookingReferenceGeneratorInterface
{
    public function generate(): string
    {
        return 'BOOK-'.strtoupper(bin2hex(random_bytes(16)));
    }
}
