<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Job;

use App\Modules\WebsiteBuilder\Application\CustomDomain\ManageCustomDomainService;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class WebsiteDesignerCustomDomainPage
{
    public function __construct(
        private WebsiteDesignerJobDetailProvider $jobs,
        private ManageCustomDomainService $domains,
    ) {}

    public function fromTrustedContext(mixed $context, string $jobId): ?DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Website Designer Custom Domain context was not established.');
        }

        $job = $this->jobs->provide($context, $jobId);
        if ($job === null) {
            return null;
        }

        $tenantId = (string) $job->data['tenantId'];
        $websiteId = (string) $job->data['websiteId'];
        $domain = $this->domains->current($tenantId, $websiteId);

        return new DashboardPageView('PlatformAdministration/Onboarding/WebsiteDesignerCustomDomain', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('onboarding', 'Onboarding', route('dashboard.onboarding'), true))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'onboarding', 'label' => 'Onboarding', 'href' => route('dashboard.onboarding')],
                [
                    'key' => 'job',
                    'label' => 'Assigned job',
                    'href' => route('dashboard.onboarding.show', ['jobId' => $jobId]),
                ],
                ['key' => 'custom-domain', 'label' => 'Custom domain add-on'],
            ],
            'pageTitle' => 'Custom domain add-on',
            'pageDescription' => 'Connect, verify and activate a clinic domain as a managed Website service.',
            'identityName' => $context->name,
            'contextLabel' => 'Website Designer workspace',
            'job' => [
                'id' => $jobId,
                'tenantId' => $tenantId,
                'websiteId' => $websiteId,
            ],
            'domain' => $domain === null ? null : [
                ...get_object_vars($domain),
                'verificationValue' => 'syifa-verification='.$this->token($domain->id),
            ],
            'operationsUrl' => route('dashboard.onboarding.custom-domain', ['jobId' => $jobId]),
            'backUrl' => route('dashboard.onboarding.show', ['jobId' => $jobId]),
        ]);
    }

    private function token(string $domainId): string
    {
        return hash_hmac('sha256', 'custom-domain|'.$domainId, (string) config('app.key'));
    }
}
