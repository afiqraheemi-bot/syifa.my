<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use DateTimeImmutable;

final readonly class LocalTime
{
    public int $minutesSinceMidnight;

    public function __construct(public string $value)
    {
        $parsed = DateTimeImmutable::createFromFormat('!H:i', $value);
        if ($parsed === false || $parsed->format('H:i') !== $value) {
            throw new InvalidClinicOperationalTimeException('Local time must use valid 24-hour H:i format.');
        }

        $this->minutesSinceMidnight = ((int) $parsed->format('H') * 60) + (int) $parsed->format('i');
    }
}
