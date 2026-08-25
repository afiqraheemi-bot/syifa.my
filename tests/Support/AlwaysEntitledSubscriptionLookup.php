<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;

/**
 * A permissive entitlement stub for tests that need a working
 * SubscriptionEntitlementLookupInterface collaborator but aren't themselves
 * exercising plan-gated behaviour.
 */
final readonly class AlwaysEntitledSubscriptionLookup implements SubscriptionEntitlementLookupInterface
{
    public function hasCapability(string $tenantId, string $capabilityKey, string $effectiveDateTime): bool
    {
        return true;
    }

    /** @return list<string> */
    public function getActiveCapabilityKeys(string $tenantId, string $effectiveDateTime): array
    {
        return [];
    }
}
