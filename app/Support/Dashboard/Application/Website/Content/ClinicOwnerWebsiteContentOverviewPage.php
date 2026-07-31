<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website\Content;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerWebsiteContentOverviewPage
{
    public function __construct(
        private WebsiteContentOverviewProvider $content,
        private ManageWebsiteContentService $editableContent,
    ) {}

    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Authenticated Website content context was not established.');
        }

        $content = $this->content->provide($context)->data;
        if ($context->tenantId === null) {
            throw new LogicException('Clinic Owner Website tenant context was not established.');
        }
        $editable = $this->editableContent->read(
            $context->tenantId,
            new WebsiteAuthorizationContext($context->identityId, $context->role, actorTenantId: $context->tenantId),
        )->toArray();

        return new DashboardPageView('TenantManagement/Website/ClinicOwnerWebsiteContentOverview', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('website', 'Website', route('dashboard.website'), false))->toArray(),
                (new DashboardNavigationItem('content', 'Content', route('dashboard.website.content'), true))->toArray(),
                (new DashboardNavigationItem('domain', 'Custom domain', route('dashboard.website.domain'), false))->toArray(),
                (new DashboardNavigationItem('services', 'Services', route('dashboard.services'), false))->toArray(),
                (new DashboardNavigationItem('bookings', 'Bookings', route('dashboard.bookings'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'website', 'label' => 'Website', 'href' => route('dashboard.website')],
                ['key' => 'content', 'label' => 'Content'],
            ],
            'pageTitle' => 'Website content',
            'pageDescription' => 'Update the governed Website configuration used by your clinic.',
            'identityName' => $context->name,
            'contextLabel' => 'Clinic Owner workspace',
            'contentHealth' => $content['health'],
            'contentSections' => $content['sections'],
            'editableContent' => $editable,
            'updateUrl' => route('dashboard.website.content.update'),
        ]);
    }
}
