<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

/**
 * The sole trusted boundary for producing an authoritative platform authorization decision.
 *
 * A decision requires all three of: an active Platform Administrator, an active Category
 * Grant connecting that administrator to the requested category, and an active Permission
 * within that category included in the grant. Role alone is not enough, and Permission
 * alone is not enough — Category Grant is required. This is platform-owned authorization
 * only: it is never Tenant authorization, never Clinic authorization, and never Subscription
 * entitlement, and it never grants RBAC, Tenant Context, or actor authority by itself.
 *
 * `effectiveDateTime` uses RFC 3339 UTC in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
 */
interface PlatformAuthorizationInterface
{
    public function authorize(
        string $administratorId,
        string $categoryKey,
        string $permissionKey,
        string $effectiveDateTime,
    ): AuthorizationDecisionData;
}
