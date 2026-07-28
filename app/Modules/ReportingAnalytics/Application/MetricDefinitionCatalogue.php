<?php

declare(strict_types=1);

namespace App\Modules\ReportingAnalytics\Application;

final readonly class MetricDefinitionCatalogue
{
    /** @return list<array{key: string, label: string, version: int, freshness: string}> */
    public function clinic(): array
    {
        return [
            $this->definition('website.publication_status', 'Website publication status'),
            $this->definition('booking.total', 'Total bookings'),
            $this->definition('booking.by_status', 'Bookings by status'),
            $this->definition('subscription.current_status', 'Current subscription status'),
        ];
    }

    /** @return list<array{key: string, label: string, version: int, freshness: string}> */
    public function designer(): array
    {
        return [
            $this->definition('onboarding.assigned_total', 'Assigned onboarding jobs'),
            $this->definition('onboarding.by_status', 'Assigned jobs by status'),
        ];
    }

    /** @return list<array{key: string, label: string, version: int, freshness: string}> */
    public function portfolio(): array
    {
        return [
            $this->definition('tenant.total', 'Total tenants'),
            $this->definition('tenant.active', 'Active tenants'),
            $this->definition('website.published', 'Published websites'),
            $this->definition('subscription.active', 'Active subscriptions'),
            $this->definition('booking.total', 'Total bookings'),
            $this->definition('onboarding.open', 'Open onboarding jobs'),
        ];
    }

    /** @return array{key: string, label: string, version: int, freshness: string} */
    private function definition(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'version' => 1, 'freshness' => 'live'];
    }
}
