<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\TenantContext\ValueObjects;

enum TenantContextRole: string
{
    case PublicVisitor = 'public_visitor';
    case ClinicOwner = 'clinic_owner';
    case WebsiteDesigner = 'website_designer';
    case SuperAdmin = 'super_admin';
}
