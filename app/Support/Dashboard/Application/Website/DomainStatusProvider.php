<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class DomainStatusProvider implements DashboardSectionProviderInterface
{
    public function __construct(private WebsitePublicAddressReadInterface $addresses) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $address = $context->tenantId === null
            ? null
            : $this->addresses->forTenant($context->tenantId);

        return new DashboardSectionProjection('domainStatus', $address === null
            ? [
                'title' => 'Website address',
                'value' => 'Preparing',
                'detail' => 'Your public Website address is being prepared.',
                'tone' => 'neutral',
                'url' => null,
                'actionLabel' => null,
            ]
            : [
                'title' => 'Website address',
                'value' => $address->active ? 'Live' : 'Preparing',
                'detail' => $address->host,
                'tone' => $address->active ? 'positive' : 'neutral',
                'url' => $address->active ? $address->url : null,
                'actionLabel' => $address->active ? 'Open Website' : null,
            ]);
    }
}
