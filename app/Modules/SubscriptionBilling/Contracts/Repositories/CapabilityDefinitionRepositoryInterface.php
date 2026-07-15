<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Repositories;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;

interface CapabilityDefinitionRepositoryInterface
{
    public function findById(CapabilityId $capabilityId): ?CapabilityDefinition;

    public function findByKey(CapabilityKey $capabilityKey): ?CapabilityDefinition;

    public function save(CapabilityDefinition $capabilityDefinition): void;

    public function existsByKey(CapabilityKey $capabilityKey): bool;
}
