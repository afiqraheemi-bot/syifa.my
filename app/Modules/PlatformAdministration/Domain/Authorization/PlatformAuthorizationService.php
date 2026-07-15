<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidCategoryGrantException;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\AuthorizationDecisionReason;

/**
 * The sole Domain evaluator of platform authorization.
 *
 * A decision requires all of: an active Platform Administrator, an active Category Grant
 * connecting that administrator to the requested category, and an active Permission within
 * that category included in the grant. Role alone is not enough, and Permission alone is not
 * enough — a Category Grant connecting all three is required, and every reference must
 * independently be active. Evaluation is fail-closed: any unmet condition denies the request.
 */
final readonly class PlatformAuthorizationService
{
    public function evaluate(
        PlatformAdministrator $administrator,
        PlatformCategory $category,
        PlatformPermission $permission,
        CategoryGrant $grant,
    ): AuthorizationDecision {
        $this->assertReferences($administrator, $category, $permission, $grant);

        if (! $administrator->isActive()) {
            return AuthorizationDecision::denied(AuthorizationDecisionReason::AdministratorNotActive);
        }

        if (! $grant->isActive()) {
            return AuthorizationDecision::denied(AuthorizationDecisionReason::GrantNotActive);
        }

        if (! $category->isActive()) {
            return AuthorizationDecision::denied(AuthorizationDecisionReason::CategoryNotActive);
        }

        if (! $permission->isActive()) {
            return AuthorizationDecision::denied(AuthorizationDecisionReason::PermissionNotActive);
        }

        if (! $grant->hasPermission($permission->key)) {
            return AuthorizationDecision::denied(AuthorizationDecisionReason::PermissionNotGranted);
        }

        return AuthorizationDecision::allowed();
    }

    private function assertReferences(
        PlatformAdministrator $administrator,
        PlatformCategory $category,
        PlatformPermission $permission,
        CategoryGrant $grant,
    ): void {
        if ($administrator->id->value !== $grant->administratorId->value
            || ! $grant->belongsToCategory($category->key)
            || ! $permission->belongsToCategory($grant->categoryKey)) {
            throw new InvalidCategoryGrantException(
                'Category Grant must be evaluated only with its referenced Platform Administrator, Category, and a Permission belonging to that Category.',
            );
        }
    }
}
