<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

/**
 * Trusted authorization decision only.
 *
 * `evaluatedAt` uses RFC 3339 UTC in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
 * This data object never grants RBAC, Tenant, or Subscription entitlement authority —
 * it answers only whether a platform-owned category action is permitted.
 */
final readonly class AuthorizationDecisionData
{
    public function __construct(
        public string $administratorId,
        public string $categoryKey,
        public string $permissionKey,
        public bool $allowed,
        public string $reason,
        public string $evaluatedAt,
    ) {}
}
