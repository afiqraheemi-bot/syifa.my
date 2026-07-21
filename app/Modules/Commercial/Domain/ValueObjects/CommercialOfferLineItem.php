<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Domain\ValueObjects;

use App\Modules\Commercial\Domain\Exceptions\InvalidCommercialOfferValueException;

final readonly class CommercialOfferLineItem
{
    public function __construct(
        public string $itemType,
        public string $itemReference,
        public string $description,
        public int $quantity,
        public PriceSnapshot $unitPrice,
        public PriceSnapshot $totalPrice,
        public string $catalogueSnapshotReference,
    ) {
        if (! preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $itemType)) {
            throw new InvalidCommercialOfferValueException('Line item type must be a stable semantic token.');
        }

        foreach ([
            'item reference' => $itemReference,
            'description' => $description,
            'catalogue snapshot reference' => $catalogueSnapshotReference,
        ] as $label => $value) {
            if (trim($value) === '' || mb_strlen($value) > 255) {
                throw new InvalidCommercialOfferValueException(sprintf('%s is invalid.', ucfirst($label)));
            }
        }

        if ($quantity < 1) {
            throw new InvalidCommercialOfferValueException('Line item quantity must be at least one.');
        }

        if ($unitPrice->currency !== $totalPrice->currency) {
            throw new InvalidCommercialOfferValueException('Line item currency must be consistent.');
        }

        if ($unitPrice->amountMinor * $quantity !== $totalPrice->amountMinor) {
            throw new InvalidCommercialOfferValueException('Line item total must equal unit price multiplied by quantity.');
        }
    }
}
