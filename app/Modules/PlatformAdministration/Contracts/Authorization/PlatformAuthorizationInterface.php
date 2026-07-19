<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

/**
 * The sole trusted boundary for producing an authoritative platform authorization decision.
 *
 * The caller supplies only the authenticated Platform Identity's own ID — never an arbitrary
 * governance-profile ID from a request body — and this boundary resolves every remaining fact
 * internally: that the identity exists and is active, that it holds the canonical Super Admin
 * role, that exactly one Platform Administrator governance profile exists for it and is active,
 * and that an active Category Grant connects that profile to the requested category and
 * includes the requested, category-matching Permission. Role alone is not enough, Permission
 * alone is not enough, and a Category Grant alone is not enough without a valid Super Admin
 * identity behind it. This is platform-owned authorization only: it is never Tenant
 * authorization, never Clinic authorization, and never Subscription entitlement, and it never
 * grants RBAC, Tenant Context, or actor authority by itself.
 *
 * `effectiveDateTime` uses RFC 3339 UTC in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
 */
interface PlatformAuthorizationInterface
{
    public function authorize(
        string $platformIdentityId,
        string $categoryKey,
        string $permissionKey,
        string $effectiveDateTime,
    ): AuthorizationDecisionData;
}
