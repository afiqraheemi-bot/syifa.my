<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Billing;

use App\Modules\SubscriptionBilling\Contracts\BillingDocument\BillingDocumentData;
use App\Modules\SubscriptionBilling\Contracts\BillingDocument\BillingDocumentReadInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
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
        private BillingDocumentReadInterface $documents,
        private PlanCatalogueQueryInterface $plans,
        private PlanOfferingCatalogueQueryInterface $offerings,
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

        $plans = [];
        foreach ($this->plans->listPlans(new OffsetPaginationInput(1, 100))->items as $plan) {
            $plans[$plan->planId] = $plan;
        }
        $today = now()->toDateString();
        $availableOfferings = [];
        foreach ($this->offerings->listPlanOfferings(new OffsetPaginationInput(1, 100))->items as $offering) {
            $plan = $plans[$offering->planId] ?? null;
            if ($offering->status !== 'active' || $plan === null || $plan->status !== 'active'
                || $offering->effectiveStart > $today
                || ($offering->effectiveEnd !== null && $offering->effectiveEnd < $today)) {
                continue;
            }
            $availableOfferings[] = [
                'id' => $offering->planOfferingId,
                'planId' => $offering->planId,
                'label' => $plan->name.' — '.$offering->currencyCode.' '.number_format($offering->amountMinor / 100, 2, '.', ','),
                'current' => $offering->planId === $detail->planId && $offering->billingOptionId === $detail->billingCycleId,
            ];
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
                (new DashboardNavigationItem('syifa-ai-usage', 'SYIFA AI Usage', route('dashboard.syifa-ai-usage'), false))->toArray(),
                (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'billing', 'label' => 'Billing', 'href' => route('dashboard.billing')],
                ['key' => 'subscription', 'label' => 'Subscription'],
            ],
            'pageTitle' => 'Subscription detail',
            'pageDescription' => 'Manage the subscriber package, renewal settings, and payment evidence.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'feedback' => $this->feedback(session('operation')),
            'subscription' => [
                'id' => $detail->subscriptionId,
                'reference' => self::reference('SUB', $detail->subscriptionId),
                'tenantId' => $detail->tenantId,
                'tenantReference' => self::reference('TEN', $detail->tenantId),
                'clinicName' => $detail->clinicName ?? 'Clinic account',
                'planId' => $detail->planId,
                'planReference' => self::reference('PLN', $detail->planId),
                'planName' => $detail->planName ?? 'Plan',
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
                'reference' => self::reference('PAY', $payment->paymentId),
                'purpose' => ucwords(str_replace('_', ' ', $payment->purpose)),
                'amount' => $payment->currency.' '.number_format($payment->amountMinor / 100, 2, '.', ','),
                'status' => ucwords(str_replace('_', ' ', $payment->status)),
                'occurredAt' => $payment->occurredAt,
            ], $this->payments->listForSubscription($subscriptionId, null, 50)),
            'documents' => array_map(static fn (BillingDocumentData $document): array => [
                'paymentId' => $document->paymentId,
                'invoiceNumber' => $document->invoiceNumber,
                'receiptNumber' => $document->receiptNumber,
                'invoiceHref' => route('dashboard.billing.invoices.show', $document->paymentId),
                'receiptHref' => $document->receiptNumber === null
                    ? null
                    : route('dashboard.billing.receipts.show', $document->paymentId),
            ], $this->documents->listForSubscription($subscriptionId)),
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
                'changePlan' => [
                    'action' => route('dashboard.billing.subscriptions.change-plan', $subscriptionId),
                    'expectedVersion' => $detail->version,
                    'offerings' => $availableOfferings,
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
            'plan_changed' => 'Subscriber package changed successfully.',
            default => null,
        };

        $error = match ($operation) {
            'not_eligible' => 'This subscription is not eligible for renewal.',
            'version_conflict' => 'The subscription changed. Refresh and try again.',
            'not_found' => 'The subscription could not be found.',
            'offering_unavailable' => 'The selected package is no longer available.',
            'same_plan' => 'The subscriber is already using that package.',
            default => null,
        };

        return ['success' => $success, 'error' => $error];
    }

    private static function reference(string $prefix, ?string $id): ?string
    {
        if ($id === null || trim($id) === '') {
            return null;
        }

        $value = trim($id);
        $withoutKnownPrefix = preg_replace(
            '/^(payment|pay|subscription|sub|tenant|ten|plan|pln)[-_]?/i',
            '',
            $value,
        ) ?? $value;
        $compact = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $withoutKnownPrefix));
        $suffix = strlen($compact) > 12 ? substr($compact, -8) : $compact;

        return $prefix.'-'.$suffix;
    }
}
