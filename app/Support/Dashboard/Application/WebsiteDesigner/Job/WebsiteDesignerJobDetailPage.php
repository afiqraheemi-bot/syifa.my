<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Job;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class WebsiteDesignerJobDetailPage
{
    public function __construct(private WebsiteDesignerJobDetailProvider $detail) {}

    public function fromTrustedContext(mixed $context, string $jobId): ?DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Website Designer dashboard context was not established.');
        }
        $job = $this->detail->provide($context, $jobId);
        if ($job === null) {
            return null;
        }

        return new DashboardPageView('PlatformAdministration/Onboarding/WebsiteDesignerJobDetail', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('onboarding', 'Onboarding', route('dashboard.onboarding'), true))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'onboarding', 'label' => 'Onboarding', 'href' => route('dashboard.onboarding')],
                ['key' => 'job', 'label' => 'Assigned job'],
            ],
            'pageTitle' => 'Assigned job detail',
            'pageDescription' => 'Review onboarding progress and operational readiness.',
            'identityName' => $context->name,
            'contextLabel' => 'Website Designer workspace',
            'job' => $job->data,
        ]);
    }
}
