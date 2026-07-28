<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Subscription;

use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\ClinicOwnerSubscriptionDetailReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerSubscriptionPage
{
    public function __construct(private ClinicOwnerSubscriptionDetailReadInterface $subscriptions) {}

    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Clinic Owner tenant context was not established.');
        }

        $detail = $this->subscriptions->detailForTenant($context->tenantId);

        return new DashboardPageView('SubscriptionBilling/Dashboard/ClinicOwnerSubscriptionDetail', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('website', 'Website', route('dashboard.website'), false))->toArray(),
                (new DashboardNavigationItem('content', 'Content', route('dashboard.website.content'), false))->toArray(),
                (new DashboardNavigationItem('bookings', 'Bookings', route('dashboard.bookings'), false))->toArray(),
                (new DashboardNavigationItem('subscription', 'Subscription', route('dashboard.subscription'), true))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'subscription', 'label' => 'Subscription'],
            ],
            'pageTitle' => 'Subscription',
            'pageDescription' => 'View your clinic plan, current term, and renewal availability.',
            'identityName' => $context->name,
            'contextLabel' => 'Clinic Owner workspace',
            'subscription' => $detail === null ? null : [
                'plan' => $detail->planName,
                'status' => $this->label($detail->status),
                'startsOn' => $detail->startsOn,
                'endsOn' => $detail->endsOn,
                'billingCycle' => $detail->billingCycleName,
                'renewalStatus' => $detail->renewalEligible ? 'Renewal available' : 'Not available',
                'latestPaymentStatus' => $detail->latestPaymentStatus === null
                    ? 'Not available'
                    : $this->label($detail->latestPaymentStatus),
            ],
            'renewal' => $detail?->renewalEligible === true ? [
                'label' => 'Renew Subscription',
                'action' => route('dashboard.subscription.renewal-checkout'),
                'csrfToken' => csrf_token(),
            ] : null,
            'feedback' => [
                'error' => session('subscription_error'),
            ],
        ]);
    }

    private function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
