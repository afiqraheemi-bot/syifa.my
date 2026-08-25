<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Audit;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use Illuminate\Support\Str;
use LogicException;

final readonly class SuperAdminAuditViewerPage
{
    public function __construct(private AuditEntryReadInterface $audit) {}

    /** @param array<string, mixed> $query */
    public function fromTrustedContext(mixed $context, array $query): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin audit context was not established.');
        }
        $value = static function (string $key, int $maximumLength = 100) use ($query): ?string {
            if (! is_string($query[$key] ?? null)) {
                return null;
            }

            $candidate = trim((string) $query[$key]);

            return $candidate !== '' && mb_strlen($candidate) <= $maximumLength ? $candidate : null;
        };
        $outcome = $value('outcome');
        $actorType = $value('actor_type');
        $tenantId = $value('tenant_id');
        $filters = [
            'action' => $value('action'),
            'outcome' => in_array($outcome, ['succeeded', 'failed', 'denied'], true) ? $outcome : null,
            'actorType' => in_array(
                $actorType,
                ['platform_identity', 'clinic_owner', 'system', 'anonymous'],
                true,
            ) ? $actorType : null,
            'tenantId' => $tenantId !== null && Str::isUuid($tenantId) ? $tenantId : null,
            'correlationId' => $value('correlation_id'),
        ];

        return new DashboardPageView('PlatformAdministration/Audit/SuperAdminAuditViewer', [
            'navigation' => $this->navigation(),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'audit', 'label' => 'Audit activity'],
            ],
            'pageTitle' => 'Aktiviti audit',
            'pageDescription' => 'Semak bukti keselamatan kekal untuk platform dan tenant.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'audit' => $this->audit->search(
                $filters['action'],
                $filters['outcome'],
                $filters['actorType'],
                $filters['tenantId'],
                $filters['correlationId'],
            ),
            'filters' => $filters,
            'indexUrl' => route('dashboard.audit'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function navigation(): array
    {
        return [
            (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
            (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray(),
            (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
            (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
            (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
            (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
            (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
            (new DashboardNavigationItem('syifa-ai-usage', 'SYIFA AI Usage', route('dashboard.syifa-ai-usage'), false))->toArray(),
            (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), true))->toArray(),
        ];
    }
}
