<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website\Content;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\ClinicOwnerDashboardNavigation;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerWebsiteContentOverviewPage
{
    public function __construct(
        private WebsiteContentOverviewProvider $content,
        private ManageWebsiteContentService $editableContent,
        private ActiveServiceReferenceReadInterface $activeServices,
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
            'navigation' => ClinicOwnerDashboardNavigation::items('content'),
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
            'templateOptions' => array_map(static fn (TemplateId $template): array => [
                'value' => $template->value,
                'label' => ucwords(strtolower(str_replace('_', ' ', $template->value))),
            ], TemplateId::cases()),
            'canChangeTemplate' => (int) $editable['published_version'] === 0,
            'updateUrl' => route('dashboard.website.content.update'),
            'previewUrl' => route('dashboard.website.preview'),
            'websiteDraft' => [
                'loadUrl' => route('clinic-owner.website-draft.show'),
                'updateUrl' => route('clinic-owner.website-draft.update'),
                'activeServices' => $this->activeServices->forTenant($context->tenantId),
                'mediaUploadUrl' => route('clinic-owner.website-assets.store'),
                'assetUrlTemplate' => route('public-website.assets.show', '__ASSET_ID__'),
            ],
            'syifaAi' => [
                'enabled' => (bool) config('syifa_ai.enabled'),
                'assistUrl' => route('clinic-owner.syifa-ai.assist'),
                'imageAssistanceEnabled' => false,
            ],
        ]);
    }
}
