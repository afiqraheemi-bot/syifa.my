<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Modules\WebsiteBuilder\Application\CustomDomain\ManageCustomDomainService;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerCustomDomainPage
{
    public function __construct(
        private WebsiteReadInterface $websites,
        private ManageCustomDomainService $domains,
    ) {}

    public function fromTrustedContext(mixed $context, string $verificationToken): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Authenticated Custom Domain context was not established.');
        }
        $website = $this->websites->detail($context->tenantId);
        if ($website === null) {
            throw new LogicException('Website was not found in the authenticated tenant.');
        }
        $domain = $this->domains->current($context->tenantId, $website->id);

        return new DashboardPageView('TenantManagement/Website/ClinicOwnerCustomDomain', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('website', 'Website', route('dashboard.website'), false))->toArray(),
                (new DashboardNavigationItem('content', 'Content', route('dashboard.website.content'), false))->toArray(),
                (new DashboardNavigationItem('domain', 'Custom domain', route('dashboard.website.domain'), true))->toArray(),
                (new DashboardNavigationItem('bookings', 'Bookings', route('dashboard.bookings'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'website', 'label' => 'Website', 'href' => route('dashboard.website')],
                ['key' => 'domain', 'label' => 'Custom domain'],
            ],
            'pageTitle' => 'Custom domain',
            'pageDescription' => 'Authorize and verify a clinic-controlled domain for your published Website.',
            'identityName' => $context->name,
            'contextLabel' => 'SYIFA.my workspace',
            'domain' => $domain === null ? null : [
                ...get_object_vars($domain),
                'verificationValue' => 'syifa-verification='.$verificationToken,
            ],
            'operationsUrl' => route('dashboard.website.domain'),
        ]);
    }
}
