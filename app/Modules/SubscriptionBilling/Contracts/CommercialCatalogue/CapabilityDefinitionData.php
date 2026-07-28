<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

final readonly class CapabilityDefinitionData
{
    public function __construct(
        public string $capabilityId,
        public string $capabilityKey,
        public string $name,
        public string $description,
        public string $commercialMeaning,
        public string $status,
        public int $version = 1,
    ) {}
}
