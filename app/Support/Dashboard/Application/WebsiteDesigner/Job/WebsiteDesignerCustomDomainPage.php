<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Job;

use App\Modules\WebsiteBuilder\Application\CustomDomain\ManageCustomDomainService;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardPageView;
use App\Support\Dashboard\Application\WebsiteDesignerDashboardNavigation;
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
            'navigation' => WebsiteDesignerDashboardNavigation::items('onboarding'),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'onboarding', 'label' => 'Onboarding', 'href' => route('dashboard.onboarding')],
                [
                    'key' => 'job',
                    'label' => 'Tugasan klinik',
                    'href' => route('dashboard.onboarding.show', ['jobId' => $jobId]),
                ],
                ['key' => 'custom-domain', 'label' => 'Custom domain'],
            ],
            'pageTitle' => 'Custom domain',
            'pageDescription' => 'Sambung, sahkan dan aktifkan domain klinik sebagai perkhidmatan website terurus.',
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
            'routingRecords' => $domain === null ? [] : $this->routingRecords($domain->hostname),
            'operationsUrl' => route('dashboard.onboarding.custom-domain', ['jobId' => $jobId]),
            'backUrl' => route('dashboard.onboarding.show', ['jobId' => $jobId]),
        ]);
    }

    private function token(string $domainId): string
    {
        return hash_hmac('sha256', 'custom-domain|'.$domainId, (string) config('app.key'));
    }

    /** @return list<array{type: string, name: string, value: string}> */
    private function routingRecords(string $hostname): array
    {
        $records = [];

        foreach ((array) config('public_website_delivery.custom_domain_targets', []) as $target) {
            $target = strtolower(rtrim(trim((string) $target), '.'));
            if ($target === '') {
                continue;
            }

            $type = filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                ? 'A'
                : (filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 'AAAA' : 'CNAME');
            $records[] = ['type' => $type, 'name' => $hostname, 'value' => $target];
        }

        return $records;
    }
}
