<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\Exceptions\InvalidPaginatedResultException;

final readonly class PaginatedCapabilityDefinitionData
{
    /**
     * @param  list<CapabilityDefinitionData>  $items
     */
    public function __construct(
        public array $items,
        public OffsetPaginationMeta $meta,
    ) {
        self::assertItemsAreACapabilityDefinitionDataList($this->items);
    }

    /** @param list<mixed> $items */
    private static function assertItemsAreACapabilityDefinitionDataList(array $items): void
    {
        $expectedIndex = 0;

        foreach ($items as $index => $item) {
            if ($index !== $expectedIndex) {
                throw new InvalidPaginatedResultException('Paginated Capability Definition items must be a list.');
            }

            if (! $item instanceof CapabilityDefinitionData) {
                throw new InvalidPaginatedResultException('Every paginated Capability Definition item must be a CapabilityDefinitionData instance.');
            }

            $expectedIndex++;
        }
    }
}
