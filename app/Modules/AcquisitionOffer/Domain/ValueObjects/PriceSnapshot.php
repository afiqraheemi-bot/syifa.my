<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Domain\ValueObjects;

use App\Modules\AcquisitionOffer\Domain\Exceptions\InvalidCommercialOfferValueException;

final readonly class PriceSnapshot
{
    public function __construct(
        public int $amountMinor,
        public string $currency,
    ) {
        if ($amountMinor < 0) {
            throw new InvalidCommercialOfferValueException('Price amount must not be negative.');
        }

        if ($currency !== 'MYR') {
            throw new InvalidCommercialOfferValueException('Commercial MVP supports MYR only.');
        }
    }
}
