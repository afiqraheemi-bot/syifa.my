<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Reports;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\ReportingAnalytics\Contracts\ReportReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use DateTimeImmutable;
use Illuminate\Support\Str;
use LogicException;

final readonly class ReportsPage
{
    public function __construct(
        private ReportReadInterface $reports,
        private AuditEntryRecorderInterface $audit,
    ) {}

    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Report context was not established.');
        }

        $report = match ($context->role) {
            'clinic_owner' => $context->tenantId === null
                ? throw new LogicException('Clinic report Tenant context is missing.')
                : $this->reports->clinic($context->tenantId),
            'website_designer' => $this->reports->designer($context->identityId),
            'super_admin' => $this->portfolio($context),
            default => throw new LogicException('Reports are unavailable for this actor.'),
        };

        return new DashboardPageView('Shared/Reports/ReportsOverview', [
            'navigation' => $this->navigation($context->role),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'reports', 'label' => 'Reports'],
            ],
            'pageTitle' => 'Reports',
            'pageDescription' => 'Review governed, live operational outcomes for your authorized scope.',
            'identityName' => $context->name,
            'contextLabel' => match ($context->role) {
                'super_admin' => 'Super Admin workspace',
                'website_designer' => 'Website Designer workspace',
                default => 'SYIFA.my workspace',
            },
            'report' => $report,
        ]);
    }

    /** @return array<string, mixed> */
    private function portfolio(AuthorizationContext $context): array
    {
        $correlationId = (string) Str::uuid();
        $this->audit->record(new AuditEntryData(
            (string) Str::uuid(),
            new DateTimeImmutable,
            new AuditActorData(AuditActorType::PlatformIdentity->value, $context->identityId),
            null,
            'report.portfolio.view',
            new AuditTargetData('report', 'platform_portfolio'),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => 'report',
                'target_label' => 'scope=platform_portfolio;purpose=mvp_outcome_oversight',
            ],
        ));

        return $this->reports->portfolio();
    }

    /** @return list<array<string, mixed>> */
    private function navigation(string $role): array
    {
        $items = [
            (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
        ];
        if ($role === 'clinic_owner') {
            $items[] = (new DashboardNavigationItem('website', 'Website', route('dashboard.website'), false))->toArray();
            $items[] = (new DashboardNavigationItem('bookings', 'Bookings', route('dashboard.bookings'), false))->toArray();
            $items[] = (new DashboardNavigationItem('subscription', 'Subscription', route('dashboard.subscription'), false))->toArray();
            $items[] = (new DashboardNavigationItem('notifications', 'Notifications', route('dashboard.notifications'), false))->toArray();
        } elseif ($role === 'website_designer') {
            $items[] = (new DashboardNavigationItem('onboarding', 'Onboarding', route('dashboard.onboarding'), false))->toArray();
        } else {
            $items[] = (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray();
            $items[] = (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray();
            $items[] = (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray();
            $items[] = (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray();
            $items[] = (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray();
            $items[] = (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray();
            $items[] = (new DashboardNavigationItem('notifications', 'Notifications', route('dashboard.notifications'), false))->toArray();
            $items[] = (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray();
        }
        $items[] = (new DashboardNavigationItem('reports', 'Reports', route('dashboard.reports'), true))->toArray();

        return $items;
    }
}
