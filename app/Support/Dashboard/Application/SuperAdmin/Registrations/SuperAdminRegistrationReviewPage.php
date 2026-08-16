<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Registrations;

use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use Carbon\CarbonImmutable;
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
        $status = is_string($query['status'] ?? null)
            && in_array($query['status'], [
                'draft',
                'submitted',
                'under_review',
                'correction_requested',
                'approved',
                'rejected',
                'provisioned',
                'cancelled',
                'expired',
            ], true)
                ? $query['status']
                : null;
        $search = is_string($query['search'] ?? null)
            ? mb_substr(trim($query['search']), 0, 100)
            : '';
        $period = is_string($query['period'] ?? null) && in_array($query['period'], ['week', 'month'], true)
            ? $query['period']
            : '';
        $scope = is_string($query['scope'] ?? null) && in_array($query['scope'], ['active', 'archived', 'all'], true)
            ? $query['scope']
            : 'active';
        [$registeredFrom, $registeredBefore] = $this->registrationPeriod($period);

        return new DashboardPageView('PlatformAdministration/Registrations/SuperAdminRegistrationReview', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), true))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
                (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
                (new DashboardNavigationItem('syifa-ai-usage', 'SYIFA AI Usage', route('dashboard.syifa-ai-usage'), false))->toArray(),
                (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'registrations', 'label' => 'Clinic Registrations'],
            ],
            'pageTitle' => 'Clinic registration review',
            'pageDescription' => 'Find, correct, review and safely archive clinic applications before commercial provisioning.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'registrations' => array_map(
                static fn ($registration): array => (array) $registration,
                $this->registrations->list(
                    $status,
                    100,
                    $search === '' ? null : $search,
                    $registeredFrom,
                    $registeredBefore,
                    $scope,
                ),
            ),
            'filters' => [
                'status' => $status,
                'search' => $search,
                'period' => $period,
                'scope' => $scope,
            ],
            'indexUrl' => route('dashboard.registrations'),
            'reviewUrlTemplate' => route('dashboard.registrations.review', ['registrationId' => '__REGISTRATION_ID__']),
            'decisionUrlTemplate' => route('dashboard.registrations.decision', ['registrationId' => '__REGISTRATION_ID__']),
            'updateUrlTemplate' => route('dashboard.registrations.update', ['registrationId' => '__REGISTRATION_ID__']),
            'archiveUrlTemplate' => route('dashboard.registrations.archive', ['registrationId' => '__REGISTRATION_ID__']),
        ]);
    }

    /** @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable} */
    private function registrationPeriod(string $period): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return match ($period) {
            'week' => [$now->startOfWeek()->utc()->toDateTimeImmutable(), $now->endOfWeek()->addMicrosecond()->utc()->toDateTimeImmutable()],
            'month' => [$now->startOfMonth()->utc()->toDateTimeImmutable(), $now->endOfMonth()->addMicrosecond()->utc()->toDateTimeImmutable()],
            default => [null, null],
        };
    }
}
