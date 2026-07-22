<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;

final readonly class OpeningInterval
{
    public function __construct(
        public LocalTime $opensAt,
        public LocalTime $closesAt,
    ) {
        if ($opensAt->minutesSinceMidnight >= $closesAt->minutesSinceMidnight) {
            throw new InvalidClinicOperationalTimeException(
                'Opening time must be before closing time; cross-midnight and 24-hour sentinel intervals are not supported.',
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->opensAt->value === $other->opensAt->value
            && $this->closesAt->value === $other->closesAt->value;
    }
}
