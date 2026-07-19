<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Resources;

use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueResourceSupport;

final class CapabilityDefinitionResource extends CommercialCatalogueResourceSupport
{
    /**
     * @param  array<string, string|null>  $links
     */
    public function __construct(
        private readonly object $resource,
        array $links = [],
    ) {
        parent::__construct($links);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'capability_id' => $this->stringProperty($this->resource, 'capabilityId') ?? $this->stringProperty($this->resource, 'id'),
            'capability_key' => $this->stringProperty($this->resource, 'capabilityKey') ?? $this->stringProperty($this->resource, 'key'),
            'name' => $this->stringProperty($this->resource, 'name'),
            'description' => $this->stringProperty($this->resource, 'description'),
            'commercial_meaning' => $this->stringProperty($this->resource, 'commercialMeaning'),
            'status' => $this->stringProperty($this->resource, 'status'),
            'version' => $this->version($this->resource),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
