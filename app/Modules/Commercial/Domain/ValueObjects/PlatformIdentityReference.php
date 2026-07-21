<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Domain\ValueObjects;

use App\Modules\Commercial\Domain\Exceptions\InvalidCommercialOfferValueException;

final readonly class PlatformIdentityReference
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(public string $value)
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new InvalidCommercialOfferValueException('Platform identity reference must be a valid UUID.');
        }
    }
}
