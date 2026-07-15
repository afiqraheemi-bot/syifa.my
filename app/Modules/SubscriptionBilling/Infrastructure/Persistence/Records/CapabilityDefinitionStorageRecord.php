<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records;

final readonly class CapabilityDefinitionStorageRecord
{
    public function __construct(
        public string $id,
        public string $key,
        public string $name,
        public string $description,
        public string $commercialMeaning,
        public string $status,
        public int $version,
    ) {}
}
