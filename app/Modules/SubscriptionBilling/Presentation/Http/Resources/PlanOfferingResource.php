<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Resources;

use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueResourceSupport;

final class PlanOfferingResource extends CommercialCatalogueResourceSupport
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
        $currentPrice = property_exists($this->resource, 'currentPrice') ? $this->resource->currentPrice : null;
        $effectivePeriod = property_exists($this->resource, 'effectivePeriod') ? $this->resource->effectivePeriod : null;

        return array_filter([
            'plan_offering_id' => $this->stringProperty($this->resource, 'planOfferingId') ?? $this->stringProperty($this->resource, 'id'),
            'plan_id' => $this->stringProperty($this->resource, 'planId'),
            'billing_option_id' => $this->stringProperty($this->resource, 'billingOptionId'),
            'amount_minor' => $currentPrice !== null ? $this->integerProperty($currentPrice, 'amountMinor') : $this->integerProperty($this->resource, 'amountMinor'),
            'currency_code' => $currentPrice !== null ? $this->stringProperty($currentPrice, 'currencyCode') : $this->stringProperty($this->resource, 'currencyCode'),
            'status' => $this->stringProperty($this->resource, 'status'),
            'effective_start' => $this->dateValue($this->propertyValue($this->resource, 'effectiveStart') ?? ($effectivePeriod !== null ? $this->propertyValue($effectivePeriod, 'startsOn') : null)),
            'effective_end' => $this->dateValue($this->propertyValue($this->resource, 'effectiveEnd') ?? ($effectivePeriod !== null ? $this->propertyValue($effectivePeriod, 'endsOn') : null)),
            'configuration_version' => $this->stringProperty($this->resource, 'configurationVersion'),
            'capability_configuration_reference' => $this->stringProperty($this->resource, 'capabilityConfigurationReference'),
            'display_order' => $this->integerProperty($this->resource, 'displayOrder'),
            'version' => $this->version($this->resource),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
