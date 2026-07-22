<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class SectionDisplayOrder
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidWebsiteValueException('Section display order must be positive.');
        }
    }
}
