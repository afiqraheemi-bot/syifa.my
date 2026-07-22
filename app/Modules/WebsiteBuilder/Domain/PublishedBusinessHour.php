<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class PublishedBusinessHour
{
    public function __construct(public int $dayOfWeek, public string $opensAt, public string $closesAt)
    {
        if ($dayOfWeek < 1 || $dayOfWeek > 7
            || preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $opensAt) !== 1
            || preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $closesAt) !== 1
            || $opensAt >= $closesAt) {
            throw new InvalidWebsiteValueException('Published business hour is invalid.');
        }
    }
}
