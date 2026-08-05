<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

enum LogoDisplaySize: string
{
    case Compact = 'compact';
    case Standard = 'standard';
    case Large = 'large';

    public static function fromStored(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidWebsiteValueException('Logo display size is invalid.');
    }
}
