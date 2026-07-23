<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class ThemeInformationProvider implements DashboardSectionProviderInterface
{
    public function __construct(private WebsiteReadInterface $websites) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $website = $context->tenantId === null ? null : $this->websites->summary($context->tenantId);

        return new DashboardSectionProjection('themeInformation', $website === null
            ? ['title' => 'Theme', 'value' => 'Not available', 'detail' => 'Theme information is not available.', 'tone' => 'neutral']
            : ['title' => 'Theme', 'value' => $website->templateId, 'detail' => 'Current Website template.', 'tone' => 'positive']);
    }
}
