<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Support\Authorization\Application\AuthorizationContext;

interface DashboardSectionProviderInterface
{
    public function provide(AuthorizationContext $context): DashboardSectionProjection;
}
