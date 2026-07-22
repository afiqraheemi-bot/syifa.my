<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

interface BookingReferenceGeneratorInterface
{
    public function generate(): string;
}
