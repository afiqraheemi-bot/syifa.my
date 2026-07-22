<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

interface BookingIdentifierGeneratorInterface
{
    public function generate(): string;
}
