<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\Exceptions\InvalidPaginatedResultException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;

final readonly class PaginatedPlanOfferingData
{
    /**
     * @param  list<PlanOfferingData>  $items
     */
    public function __construct(
        public array $items,
        public OffsetPaginationMeta $meta,
    ) {
        self::assertItemsAreAPlanOfferingDataList($this->items);
    }

    /** @param list<mixed> $items */
    private static function assertItemsAreAPlanOfferingDataList(array $items): void
    {
        $expectedIndex = 0;

        foreach ($items as $index => $item) {
            if ($index !== $expectedIndex) {
                throw new InvalidPaginatedResultException('Paginated Plan Offering items must be a list.');
            }

            if (! $item instanceof PlanOfferingData) {
                throw new InvalidPaginatedResultException('Every paginated Plan Offering item must be a PlanOfferingData instance.');
            }

            $expectedIndex++;
        }
    }
}
