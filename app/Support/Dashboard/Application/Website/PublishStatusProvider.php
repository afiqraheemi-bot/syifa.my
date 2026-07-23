<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Modules\WebsiteBuilder\Contracts\Queries\WebsitePublishedSnapshotReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class PublishStatusProvider implements DashboardSectionProviderInterface
{
    public function __construct(
        private WebsiteReadInterface $websites,
        private WebsitePublishedSnapshotReadInterface $snapshots,
    ) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $website = $context->tenantId === null ? null : $this->websites->summary($context->tenantId);
        $snapshot = $website === null ? null : $this->snapshots->latest($website->id);

        return new DashboardSectionProjection('publishStatus', $snapshot === null
            ? ['title' => 'Website health', 'value' => 'Not published', 'detail' => 'No published Website snapshot is available.', 'tone' => 'neutral']
            : ['title' => 'Website health', 'value' => 'Published', 'detail' => sprintf('Version %d published %s.', $snapshot->publishedVersion, $snapshot->publishedAt->format('Y-m-d')), 'tone' => 'positive']);
    }
}
