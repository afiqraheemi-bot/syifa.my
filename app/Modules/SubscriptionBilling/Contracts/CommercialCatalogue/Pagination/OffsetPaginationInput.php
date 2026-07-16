<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination;

use InvalidArgumentException;

final readonly class OffsetPaginationInput
{
    public function __construct(
        public int $page,
        public int $perPage,
    ) {
        if ($this->page < 1) {
            throw new InvalidArgumentException('Pagination page must be at least 1.');
        }

        if ($this->perPage < 1) {
            throw new InvalidArgumentException('Pagination perPage must be at least 1.');
        }

        if ($this->perPage > 100) {
            throw new InvalidArgumentException('Pagination perPage must not exceed 100 in Phase 1.');
        }
    }
}
