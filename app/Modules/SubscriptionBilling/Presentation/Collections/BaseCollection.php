<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Collections;

use App\Modules\SubscriptionBilling\Presentation\Resources\BaseResource;

abstract class BaseCollection
{
    /**
     * @param  list<BaseResource>  $items
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $links
     */
    public function __construct(
        private readonly array $items,
        private readonly array $meta = [],
        private readonly array $links = [],
    ) {}

    /**
     * @return list<BaseResource>
     */
    final public function items(): array
    {
        return $this->items;
    }

    /**
     * @return array<string, mixed>
     */
    final public function meta(): array
    {
        return $this->meta;
    }

    /**
     * @return array<string, string>
     */
    final public function links(): array
    {
        return $this->links;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (BaseResource $item): array => $item->toArray(),
            $this->items,
        );
    }
}
