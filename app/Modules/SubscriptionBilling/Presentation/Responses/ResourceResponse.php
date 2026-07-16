<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Responses;

use App\Modules\SubscriptionBilling\Presentation\Resources\BaseResource;

final class ResourceResponse extends BaseApiResponse
{
    public function __construct(
        string $correlationId,
        public readonly BaseResource $resource,
    ) {
        parent::__construct($correlationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->resource->toArray(),
            'links' => $this->resource->links(),
            'correlation_id' => $this->correlationId,
        ];
    }
}
