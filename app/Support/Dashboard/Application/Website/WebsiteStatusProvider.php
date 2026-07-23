<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class WebsiteStatusProvider implements DashboardSectionProviderInterface
{
    public function __construct(private WebsiteReadInterface $websites) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $website = $context->tenantId === null ? null : $this->websites->detail($context->tenantId);

        return new DashboardSectionProjection('websiteStatus', $website === null
            ? ['title' => 'Website overview', 'value' => 'Not available', 'detail' => 'No Website is available for this clinic.', 'tone' => 'neutral']
            : [
                'title' => $website->clinicName,
                'value' => ucfirst(str_replace('_', ' ', $website->lifecycle)),
                'detail' => implode(' · ', array_filter([
                    $website->tagline,
                    $website->contactEmail,
                    $website->contactPhone,
                    $website->address,
                ], static fn (?string $value): bool => $value !== null && $value !== '')),
                'tone' => $website->lifecycle === 'published' ? 'positive' : 'neutral',
            ]);
    }
}
