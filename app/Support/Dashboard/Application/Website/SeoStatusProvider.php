<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSeoSummaryReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class SeoStatusProvider implements DashboardSectionProviderInterface
{
    public function __construct(
        private WebsiteReadInterface $websites,
        private WebsiteSeoSummaryReadInterface $seo,
    ) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $website = $context->tenantId === null ? null : $this->websites->summary($context->tenantId);
        $seo = $website === null ? null : $this->seo->summary($website->id);

        return new DashboardSectionProjection('seoStatus', $seo === null
            ? ['title' => 'SEO', 'value' => 'Not available', 'detail' => 'SEO information is not available.', 'tone' => 'neutral']
            : ['title' => 'SEO', 'value' => $seo->indexingEnabled ? 'Indexing enabled' : 'Indexing disabled', 'detail' => $seo->metaTitle, 'tone' => $seo->indexingEnabled ? 'positive' : 'neutral']);
    }
}
