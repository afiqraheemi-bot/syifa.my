<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\Exceptions\InvalidPaginatedResultException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;

final readonly class PaginatedPlanData
{
    /**
     * @param  list<PlanData>  $items
     */
    public function __construct(
        public array $items,
        public OffsetPaginationMeta $meta,
    ) {
        self::assertItemsAreAPlanDataList($this->items);
    }

    /** @param list<mixed> $items */
    private static function assertItemsAreAPlanDataList(array $items): void
    {
        $expectedIndex = 0;

        foreach ($items as $index => $item) {
            if ($index !== $expectedIndex) {
                throw new InvalidPaginatedResultException('Paginated Plan items must be a list.');
            }

            if (! $item instanceof PlanData) {
                throw new InvalidPaginatedResultException('Every paginated Plan item must be a PlanData instance.');
            }

            $expectedIndex++;
        }
    }
}
