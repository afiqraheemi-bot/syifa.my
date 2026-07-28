<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

final readonly class PricingHistoryData
{
    public function __construct(
        public int $version,
        public int $amountMinor,
        public string $currencyCode,
        public string $effectiveStart,
        public ?string $effectiveEnd,
        public string $capabilityConfigurationReference,
        public string $recordedAt,
    ) {}
}
