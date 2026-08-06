<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;
use App\Modules\Onboarding\Contracts\Tasks\OnboardingTaskReadInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\ClinicOwnerWebsiteApprovalReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\ClinicOwnerDashboardNavigation;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerWebsiteOverviewPage
{
    public function __construct(
        private WebsiteStatusProvider $website,
        private PublishStatusProvider $publish,
        private DomainStatusProvider $domain,
        private ThemeInformationProvider $theme,
        private SeoStatusProvider $seo,
        private WebsiteQuickActionsProvider $quickActions,
        private ClinicOwnerWebsiteApprovalReadInterface $approval,
        private OnboardingTaskReadInterface $onboardingTasks,
        private LaunchReadinessReadInterface $launchReadiness,
    ) {}

    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Authenticated Website dashboard context was not established.');
        }
        $approval = $context->tenantId === null ? null : $this->approval->forTenant($context->tenantId);
        $tasks = $context->tenantId === null ? null : $this->onboardingTasks->forTenant($context->tenantId);
        $readiness = $context->tenantId === null ? null : $this->launchReadiness->forTenant($context->tenantId);

        return new DashboardPageView('TenantManagement/Website/ClinicOwnerWebsiteOverview', [
            'navigation' => ClinicOwnerDashboardNavigation::items('website'),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'website', 'label' => 'Website'],
            ],
            'pageTitle' => 'Website overview',
            'pageDescription' => 'Review the current state of your clinic Website.',
            'identityName' => $context->name,
            'contextLabel' => 'Clinic Owner workspace',
            'websiteStatus' => $this->website->provide($context)->data,
            'publishStatus' => $this->publish->provide($context)->data,
            'domainStatus' => $this->domain->provide($context)->data,
            'themeInformation' => $this->theme->provide($context)->data,
            'seoStatus' => $this->seo->provide($context)->data,
            'quickActions' => $this->quickActions->provide($context)->data,
            'websiteApproval' => $approval,
            'websiteApprovalDecisionUrl' => route('dashboard.website.approval'),
            'websiteApprovalPreviewUrl' => route('dashboard.website.preview'),
            'onboardingTasks' => $tasks,
            'launchReadiness' => $readiness?->toArray(),
            'onboardingTaskUrlTemplate' => $tasks === null ? null : route('dashboard.website.onboarding-tasks.update', [
                'jobId' => $tasks['jobId'],
                'taskId' => '__TASK_ID__',
            ]),
        ]);
    }
}
