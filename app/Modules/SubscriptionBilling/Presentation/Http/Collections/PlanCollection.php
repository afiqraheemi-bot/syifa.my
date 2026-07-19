<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Collections;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationMeta;
use App\Modules\SubscriptionBilling\Presentation\Collections\BaseCollection;
use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCataloguePaginationLinkBuilder;
use App\Modules\SubscriptionBilling\Presentation\Resources\BaseResource;

final class PlanCollection extends BaseCollection
{
    /**
     * @param  list<BaseResource>  $items
     */
    public static function fromPagination(string $path, array $items, OffsetPaginationMeta $meta): self
    {
        return new self(
            $items,
            [
                'current_page' => $meta->currentPage,
                'per_page' => $meta->perPage,
                'total' => $meta->total,
                'last_page' => $meta->lastPage,
                'from' => $meta->from,
                'to' => $meta->to,
            ],
            CommercialCataloguePaginationLinkBuilder::build($path, $meta),
        );
    }
}
