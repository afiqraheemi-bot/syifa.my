<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Entitlements;

interface SubscriptionEntitlementLookupInterface
{
    /**
     * Unknown Tenant, invalid Tenant, inactive Subscription, expired Subscription, or unavailable entitlement must fail closed.
     * Returns false when capability access cannot be established.
     * `effectiveDateTime` uses RFC 3339 UTC in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
     *
     * @param  string  $effectiveDateTime  RFC 3339 UTC instant in `YYYY-MM-DDTHH:MM:SSZ` format.
     */
    public function hasCapability(
        string $tenantId,
        string $capabilityKey,
        string $effectiveDateTime,
    ): bool;

    /**
     * Unknown Tenant, invalid Tenant, inactive Subscription, expired Subscription, or unavailable entitlement must fail closed.
     * Returns an empty list when capability access cannot be established.
     * `effectiveDateTime` uses RFC 3339 UTC in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
     * The returned capability keys are commercial entitlement only, unique, and deterministic.
     *
     * @param  string  $effectiveDateTime  RFC 3339 UTC instant in `YYYY-MM-DDTHH:MM:SSZ` format.
     * @return list<string>
     */
    public function getActiveCapabilityKeys(
        string $tenantId,
        string $effectiveDateTime,
    ): array;
}
