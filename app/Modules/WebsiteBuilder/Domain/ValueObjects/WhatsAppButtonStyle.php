<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

enum WhatsAppButtonStyle: string
{
    case Pill = 'pill';
    case Circle = 'circle';
    case RoundedSquare = 'rounded_square';

    public static function fromStored(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidWebsiteValueException('WhatsApp button style is invalid.');
    }
}
