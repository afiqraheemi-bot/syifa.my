<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Onboarding;

use App\Modules\Onboarding\Contracts\Administration\SuperAdminOnboardingReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class SuperAdminOnboardingPage
{
    public function __construct(private SuperAdminOnboardingReadInterface $onboarding) {}

    /** @param array<string, mixed> $query */
    public function fromTrustedContext(mixed $context, array $query): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }
        $status = is_string($query['status'] ?? null) && $query['status'] !== '' ? $query['status'] : null;
        $search = is_string($query['search'] ?? null) && trim($query['search']) !== '' ? trim($query['search']) : null;

        return new DashboardPageView('PlatformAdministration/Onboarding/SuperAdminOnboarding', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
                (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), true))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
                (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'onboarding', 'label' => 'Onboarding'],
            ],
            'pageTitle' => 'Onboarding management',
            'pageDescription' => 'Assign eligible Website Designers and monitor every provisioned clinic.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'onboarding' => $this->onboarding->overview($status, $search),
            'filters' => ['status' => $status, 'search' => $search],
            'assignUrlTemplate' => route('dashboard.onboarding-management.assign', ['jobId' => '__JOB_ID__']),
            'reassignUrlTemplate' => route('dashboard.onboarding-management.reassign', ['jobId' => '__JOB_ID__']),
            'lifecycleUrlTemplate' => route('dashboard.onboarding-management.lifecycle', ['jobId' => '__JOB_ID__']),
            'taskUrlTemplate' => route('dashboard.onboarding-management.tasks.update', [
                'jobId' => '__JOB_ID__',
                'taskId' => '__TASK_ID__',
            ]),
            'ownerUrlTemplate' => route('dashboard.onboarding-management.owner', ['tenantId' => '__TENANT_ID__']),
        ]);
    }
}
