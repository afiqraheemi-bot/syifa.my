<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\Exceptions\InvalidPaginatedResultException;

final readonly class PaginatedBillingOptionData
{
    /**
     * @param  list<BillingOptionData>  $items
     */
    public function __construct(
        public array $items,
        public OffsetPaginationMeta $meta,
    ) {
        self::assertItemsAreABillingOptionDataList($this->items);
    }

    /** @param list<mixed> $items */
    private static function assertItemsAreABillingOptionDataList(array $items): void
    {
        $expectedIndex = 0;

        foreach ($items as $index => $item) {
            if ($index !== $expectedIndex) {
                throw new InvalidPaginatedResultException('Paginated Billing Option items must be a list.');
            }

            if (! $item instanceof BillingOptionData) {
                throw new InvalidPaginatedResultException('Every paginated Billing Option item must be a BillingOptionData instance.');
            }

            $expectedIndex++;
        }
    }
}
