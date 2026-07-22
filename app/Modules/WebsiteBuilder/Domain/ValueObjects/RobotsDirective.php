<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

enum RobotsDirective: string
{
    case IndexFollow = 'index,follow';
    case IndexNoFollow = 'index,nofollow';
    case NoIndexFollow = 'noindex,follow';
    case NoIndexNoFollow = 'noindex,nofollow';

    public static function fromStored(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidWebsiteValueException('Stored robots directive is invalid.');
    }
}
