<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Notifications;

use App\Modules\Notification\Contracts\NotificationReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use Illuminate\Support\Str;
use LogicException;

final readonly class NotificationHistoryPage
{
    public function __construct(private NotificationReadInterface $notifications) {}

    /** @param array<string, mixed> $query */
    public function fromTrustedContext(mixed $context, array $query): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Notification history context was not established.');
        }

        $status = $this->allowed($query['status'] ?? null, [
            'prepared', 'queued', 'sent', 'delivered', 'delayed', 'failed', 'suppressed', 'cancelled', 'exhausted',
        ]);
        $triggerType = $this->text($query['trigger_type'] ?? null);
        $superAdmin = $context->role === 'super_admin';
        if (! $superAdmin && ($context->role !== 'clinic_owner' || $context->tenantId === null)) {
            throw new LogicException('Notification history is unavailable for this actor.');
        }
        $requestedTenant = $this->text($query['tenant_id'] ?? null);
        $tenantFilter = $superAdmin && $requestedTenant !== null && Str::isUuid($requestedTenant)
            ? $requestedTenant
            : ($superAdmin ? null : $context->tenantId);

        return new DashboardPageView('Shared/Notifications/NotificationHistory', [
            'navigation' => $this->navigation($superAdmin),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'notifications', 'label' => 'Notifications'],
            ],
            'pageTitle' => 'Notifications',
            'pageDescription' => 'Review transactional communication and delivery outcomes.',
            'identityName' => $context->name,
            'contextLabel' => $superAdmin ? 'Super Admin workspace' : 'Clinic Owner workspace',
            'notificationHistory' => $superAdmin
                ? $this->notifications->forPlatform($tenantFilter, $status, $triggerType)
                : $this->notifications->forTenant($context->tenantId, $status, $triggerType),
            'filters' => [
                'status' => $status,
                'triggerType' => $triggerType,
                'tenantId' => $superAdmin ? $tenantFilter : null,
            ],
            'canFilterTenant' => $superAdmin,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function navigation(bool $superAdmin): array
    {
        if ($superAdmin) {
            return [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
                (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
                (new DashboardNavigationItem('notifications', 'Notifications', route('dashboard.notifications'), true))->toArray(),
                (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
            ];
        }

        return [
            (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
            (new DashboardNavigationItem('website', 'Website', route('dashboard.website'), false))->toArray(),
            (new DashboardNavigationItem('content', 'Content', route('dashboard.website.content'), false))->toArray(),
            (new DashboardNavigationItem('bookings', 'Bookings', route('dashboard.bookings'), false))->toArray(),
            (new DashboardNavigationItem('subscription', 'Subscription', route('dashboard.subscription'), false))->toArray(),
            (new DashboardNavigationItem('notifications', 'Notifications', route('dashboard.notifications'), true))->toArray(),
        ];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' && mb_strlen(trim($value)) <= 100
            ? trim($value)
            : null;
    }

    /** @param list<string> $allowed */
    private function allowed(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }
}
