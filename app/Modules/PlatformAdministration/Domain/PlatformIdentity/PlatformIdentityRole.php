<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\PlatformIdentity;

enum PlatformIdentityRole: string
{
    case WebsiteDesigner = 'website_designer';
    case SuperAdmin = 'super_admin';
}
