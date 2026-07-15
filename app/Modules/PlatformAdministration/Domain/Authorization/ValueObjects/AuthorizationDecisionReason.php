<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects;

enum AuthorizationDecisionReason: string
{
    case Allowed = 'allowed';
    case AdministratorNotActive = 'administrator_not_active';
    case GrantNotActive = 'grant_not_active';
    case CategoryNotActive = 'category_not_active';
    case PermissionNotActive = 'permission_not_active';
    case PermissionNotGranted = 'permission_not_granted';
}
