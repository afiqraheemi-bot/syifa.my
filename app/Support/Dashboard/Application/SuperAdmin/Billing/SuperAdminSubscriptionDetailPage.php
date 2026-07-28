<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Billing;

use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\PaymentHistoryReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionDetailReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionPaymentData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionTimelineData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionTimelineReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class SuperAdminSubscriptionDetailPage
{
    public function __construct(
        private SubscriptionDetailReadInterface $subscriptions,
        private SubscriptionTimelineReadInterface $timeline,
        private PaymentHistoryReadInterface $payments,
    ) {}

    public function fromTrustedContext(mixed $context, string $subscriptionId): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }
        $detail = $this->subscriptions->detail($subscriptionId);
        if ($detail === null) {
            throw new NotFoundHttpException('Subscription was not found.');
        }

        return new DashboardPageView('SubscriptionBilling/Dashboard/SuperAdminSubscriptionDetail', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
                (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), true))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
                (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'billing', 'label' => 'Billing', 'href' => route('dashboard.billing')],
                ['key' => 'subscription', 'label' => 'Subscription'],
            ],
            'pageTitle' => 'Subscription detail',
            'pageDescription' => 'Read-only commercial lifecycle and payment evidence.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'feedback' => $this->feedback(session('operation')),
            'subscription' => [
                'id' => $detail->subscriptionId,
                'tenantId' => $detail->tenantId,
                'planId' => $detail->planId,
                'billingCycleId' => $detail->billingCycleId,
                'amount' => $detail->currency.' '.number_format($detail->amountMinor / 100, 2, '.', ','),
                'startsOn' => $detail->startsOn,
                'endsOn' => $detail->endsOn,
                'status' => ucwords(str_replace('_', ' ', $detail->status)),
                'renewalStatus' => ucwords(str_replace('_', ' ', $detail->renewalStatus)),
                'autoRenewStatus' => ucwords(str_replace('_', ' ', $detail->autoRenewStatus)),
                'version' => $detail->version,
            ],
            'timeline' => array_map(static fn (SubscriptionTimelineData $item): array => [
                'id' => $item->id,
                'label' => ucwords(str_replace('_', ' ', $item->eventType)),
                'occurredAt' => $item->occurredAt,
            ], $this->timeline->list($subscriptionId, null, 50)),
            'payments' => array_map(static fn (SubscriptionPaymentData $payment): array => [
                'id' => $payment->paymentId,
                'purpose' => ucwords(str_replace('_', ' ', $payment->purpose)),
                'amount' => $payment->currency.' '.number_format($payment->amountMinor / 100, 2, '.', ','),
                'status' => ucwords(str_replace('_', ' ', $payment->status)),
                'occurredAt' => $payment->occurredAt,
            ], $this->payments->listForSubscription($subscriptionId, null, 50)),
            'actions' => [
                'csrfToken' => csrf_token(),
                'renew' => [
                    'label' => 'Request manual renewal',
                    'action' => route('dashboard.billing.subscriptions.renew', $subscriptionId),
                    'expectedVersion' => $detail->version,
                    'idempotencyKey' => (string) Str::uuid(),
                ],
                'autoRenew' => [
                    'enabled' => $detail->autoRenewStatus === 'enabled',
                    'enableAction' => route('dashboard.billing.subscriptions.auto-renew.enable', $subscriptionId),
                    'disableAction' => route('dashboard.billing.subscriptions.auto-renew.disable', $subscriptionId),
                    'expectedVersion' => $detail->version,
                ],
                'checkout' => $detail->renewalCheckoutId === null ? null : [
                    'label' => 'Start Renewal Checkout',
                    'action' => route('renewal-checkouts.start', $detail->renewalCheckoutId),
                ],
            ],
        ]);
    }

    /** @return array{success: ?string, error: ?string} */
    private function feedback(mixed $operation): array
    {
        if (! is_string($operation)) {
            return ['success' => null, 'error' => null];
        }

        $success = match ($operation) {
            'accepted' => 'Manual renewal requested successfully.',
            'already_accepted' => 'This renewal request was already accepted.',
            'enabled' => 'Auto-renew enabled successfully.',
            'already_enabled' => 'Auto-renew is already enabled.',
            'cancelled' => 'Auto-renew disabled successfully.',
            'already_cancelled' => 'Auto-renew is already disabled.',
            default => null,
        };

        $error = match ($operation) {
            'not_eligible' => 'This subscription is not eligible for renewal.',
            'version_conflict' => 'The subscription changed. Refresh and try again.',
            'not_found' => 'The subscription could not be found.',
            default => null,
        };

        return ['success' => $success, 'error' => $error];
    }
}
