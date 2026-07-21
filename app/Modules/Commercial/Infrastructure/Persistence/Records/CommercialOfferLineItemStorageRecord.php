<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\Persistence\Records;

final readonly class CommercialOfferLineItemStorageRecord
{
    public function __construct(
        public string $commercialOfferId,
        public string $itemType,
        public string $itemReference,
        public string $description,
        public int $quantity,
        public int $unitAmountMinor,
        public int $totalAmountMinor,
        public string $currency,
        public string $catalogueSnapshotReference,
        public int $position,
    ) {}
}
