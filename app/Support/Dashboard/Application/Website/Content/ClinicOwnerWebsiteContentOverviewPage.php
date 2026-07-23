<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website\Content;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerWebsiteContentOverviewPage
{
    public function __construct(private WebsiteContentOverviewProvider $content) {}

    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Authenticated Website content context was not established.');
        }

        $content = $this->content->provide($context)->data;

        return new DashboardPageView('TenantManagement/Website/ClinicOwnerWebsiteContentOverview', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('website', 'Website', route('dashboard.website'), false))->toArray(),
                (new DashboardNavigationItem('content', 'Content', route('dashboard.website.content'), true))->toArray(),
                (new DashboardNavigationItem('bookings', 'Bookings', route('dashboard.bookings'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'website', 'label' => 'Website', 'href' => route('dashboard.website')],
                ['key' => 'content', 'label' => 'Content'],
            ],
            'pageTitle' => 'Website content',
            'pageDescription' => 'Review the completion of published Website content.',
            'identityName' => $context->name,
            'contextLabel' => 'SYIFA.my workspace',
            'contentHealth' => $content['health'],
            'contentSections' => $content['sections'],
        ]);
    }
}
