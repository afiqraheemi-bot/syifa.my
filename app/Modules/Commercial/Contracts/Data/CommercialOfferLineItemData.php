<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Data;

final readonly class CommercialOfferLineItemData
{
    public function __construct(
        public string $itemType,
        public string $itemReference,
        public string $description,
        public int $quantity,
        public int $unitAmountMinor,
        public int $totalAmountMinor,
        public string $currency,
        public string $catalogueSnapshotReference,
    ) {}
}
