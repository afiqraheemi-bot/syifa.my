<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Responses;

use App\Modules\SubscriptionBilling\Presentation\Collections\BaseCollection;

final class CollectionResponse extends BaseApiResponse
{
    public function __construct(
        string $correlationId,
        public readonly BaseCollection $collection,
    ) {
        parent::__construct($correlationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->collection->toArray(),
            'meta' => $this->collection->meta(),
            'links' => $this->collection->links(),
            'correlation_id' => $this->correlationId,
        ];
    }
}
