<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Resources;

use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueResourceSupport;

final class PlanResource extends CommercialCatalogueResourceSupport
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
        $status = $this->stringProperty($this->resource, 'status');

        if ($status === null && property_exists($this->resource, 'lifecycle') && is_object($this->resource->lifecycle)) {
            $status = $this->stringProperty($this->resource->lifecycle, 'status');
        }

        return array_filter([
            'plan_id' => $this->stringProperty($this->resource, 'planId') ?? $this->stringProperty($this->resource, 'id'),
            'code' => $this->stringProperty($this->resource, 'code'),
            'name' => $this->stringProperty($this->resource, 'name'),
            'description' => $this->stringProperty($this->resource, 'description'),
            'status' => $status,
            'display_order' => $this->integerProperty($this->resource, 'displayOrder'),
            'created_at' => $this->dateTimeValue($this->propertyValue($this->resource, 'createdAt') ?? $this->propertyValue($this->resource, 'created_at')),
            'last_changed_at' => $this->dateTimeValue($this->propertyValue($this->resource, 'lastChangedAt') ?? $this->propertyValue($this->resource, 'last_changed_at')),
            'version' => $this->version($this->resource),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
