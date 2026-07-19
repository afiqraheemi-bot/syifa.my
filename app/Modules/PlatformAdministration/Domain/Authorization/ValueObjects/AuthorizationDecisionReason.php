<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects;

/**
 * `PlatformIdentityNotFound`, `PlatformIdentityNotActive`, and `SuperAdminRoleRequired`
 * originate from the Application-layer authorization service's own identity/role fact
 * resolution — they have no Domain evaluation counterpart, because PlatformAuthorizationService
 * has no notion of Platform Identity at all. `AdministratorProfileNotFound` and
 * `AdministratorProfileAmbiguous` are likewise Application-resolved missing/ambiguous-fact
 * outcomes produced before any Domain object is ever constructed. Every other case is produced
 * only by PlatformAuthorizationService::evaluate() once every required fact has actually been
 * established. An unexpected Infrastructure failure is never translated into any case here —
 * it propagates as an Infrastructure exception instead.
 */
enum AuthorizationDecisionReason: string
{
    case Allowed = 'allowed';
    case AdministratorNotActive = 'administrator_not_active';
    case GrantNotActive = 'grant_not_active';
    case CategoryNotActive = 'category_not_active';
    case PermissionNotActive = 'permission_not_active';
    case PermissionNotGranted = 'permission_not_granted';
    case CategoryNotFound = 'category_not_found';
    case PermissionNotFound = 'permission_not_found';
    case GrantNotFound = 'grant_not_found';
    case PermissionCategoryMismatch = 'permission_category_mismatch';
    case PlatformIdentityNotFound = 'platform_identity_not_found';
    case PlatformIdentityNotActive = 'platform_identity_not_active';
    case SuperAdminRoleRequired = 'super_admin_role_required';
    case AdministratorProfileNotFound = 'administrator_profile_not_found';
    case AdministratorProfileAmbiguous = 'administrator_profile_ambiguous';
}
