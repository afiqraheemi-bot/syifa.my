<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Support;

use App\Modules\SubscriptionBilling\Presentation\Resources\BaseResource;
use DateTimeInterface;

abstract class CommercialCatalogueResourceSupport extends BaseResource
{
    protected function propertyValue(object $resource, string $property): mixed
    {
        if (! property_exists($resource, $property)) {
            return null;
        }

        return $resource->{$property};
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(object $resource): array
    {
        $metadata = ['version' => $this->version($resource)];

        return array_filter($metadata, static fn (mixed $value): bool => $value !== null);
    }

    protected function stringProperty(object $resource, string $property): ?string
    {
        $value = $this->propertyValue($resource, $property);

        if (is_string($value)) {
            return $value;
        }

        if (is_object($value) && property_exists($value, 'value') && is_string($value->value)) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        return null;
    }

    protected function integerProperty(object $resource, string $property): ?int
    {
        $value = $this->propertyValue($resource, $property);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        if (is_object($value) && property_exists($value, 'amountMinor') && is_int($value->amountMinor)) {
            return $value->amountMinor;
        }

        return null;
    }

    protected function version(object $resource): ?int
    {
        if (method_exists($resource, 'version')) {
            $version = $resource->version();

            return is_int($version) ? $version : null;
        }

        if (property_exists($resource, 'version')) {
            $value = $resource->version;

            return is_int($value) ? $value : null;
        }

        return null;
    }

    protected function dateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return null;
    }

    protected function dateTimeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        return null;
    }
}
