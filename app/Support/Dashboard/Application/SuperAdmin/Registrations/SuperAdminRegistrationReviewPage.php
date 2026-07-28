<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Registrations;

use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class SuperAdminRegistrationReviewPage
{
    public function __construct(private ClinicRegistrationReviewReadInterface $registrations) {}

    /** @param array<string, mixed> $query */
    public function fromTrustedContext(mixed $context, array $query): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }
        $status = is_string($query['status'] ?? null) && $query['status'] !== ''
            ? $query['status']
            : null;

        return new DashboardPageView('PlatformAdministration/Registrations/SuperAdminRegistrationReview', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), true))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
                (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
                (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'registrations', 'label' => 'Clinic Registrations'],
            ],
            'pageTitle' => 'Clinic registration review',
            'pageDescription' => 'Review prospective clinics before any commercial checkout or tenant provisioning.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'registrations' => array_map(
                static fn ($registration): array => (array) $registration,
                $this->registrations->list($status),
            ),
            'filters' => ['status' => $status],
            'reviewUrlTemplate' => route('dashboard.registrations.review', ['registrationId' => '__REGISTRATION_ID__']),
            'decisionUrlTemplate' => route('dashboard.registrations.decision', ['registrationId' => '__REGISTRATION_ID__']),
        ]);
    }
}
