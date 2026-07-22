<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

enum PublicationResult: string
{
    case Published = 'published';

    public static function fromStored(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidWebsiteValueException('Stored publication result is invalid.');
    }
}
