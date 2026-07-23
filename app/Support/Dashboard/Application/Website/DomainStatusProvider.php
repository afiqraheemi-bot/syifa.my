<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class DomainStatusProvider implements DashboardSectionProviderInterface
{
    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('domainStatus', [
            'title' => 'Domain',
            'value' => 'Not available',
            'detail' => 'Domain management is not implemented yet.',
            'tone' => 'neutral',
        ]);
    }
}
