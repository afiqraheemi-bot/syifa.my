<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Resources;

use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueResourceSupport;

final class BillingOptionResource extends CommercialCatalogueResourceSupport
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
        $duration = property_exists($this->resource, 'duration') ? $this->resource->duration : null;
        $effectivePeriod = property_exists($this->resource, 'effectivePeriod') ? $this->resource->effectivePeriod : null;

        return array_filter([
            'billing_option_id' => $this->stringProperty($this->resource, 'billingOptionId') ?? $this->stringProperty($this->resource, 'id'),
            'code' => $this->stringProperty($this->resource, 'code'),
            'name' => $this->stringProperty($this->resource, 'name'),
            'availability' => $this->stringProperty($this->resource, 'availability'),
            'recurrence_classification' => $this->stringProperty($this->resource, 'recurrenceClassification') ?? $this->stringProperty($this->resource, 'recurrence'),
            'interval_unit' => $duration !== null ? $this->stringProperty($duration, 'interval') ?? $this->stringProperty($duration, 'intervalUnit') : $this->stringProperty($this->resource, 'intervalUnit'),
            'interval_count' => $duration !== null ? $this->integerProperty($duration, 'intervalCount') : $this->integerProperty($this->resource, 'intervalCount'),
            'effective_start' => $this->dateValue($this->propertyValue($this->resource, 'effectiveStart') ?? ($effectivePeriod !== null ? $this->propertyValue($effectivePeriod, 'startsOn') : null)),
            'effective_end' => $this->dateValue($this->propertyValue($this->resource, 'effectiveEnd') ?? ($effectivePeriod !== null ? $this->propertyValue($effectivePeriod, 'endsOn') : null)),
            'display_order' => $this->integerProperty($this->resource, 'displayOrder'),
            'version' => $this->version($this->resource),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
