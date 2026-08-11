<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Billing;

use App\Modules\SubscriptionBilling\Contracts\BillingOverview\BillingOverviewReadInterface;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\RecentPaymentData;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\SubscriptionOverviewData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;

final readonly class BillingOverviewProvider
{
    public function __construct(private BillingOverviewReadInterface $billing) {}

    public function provide(
        AuthorizationContext $context,
        BillingOverviewCriteria $criteria,
        string $asOfDate,
    ): DashboardSectionProjection {
        $searchTerm = self::searchTerm($criteria->search);
        $summary = $this->billing->summary($asOfDate);
        $rows = $this->billing->subscriptions(
            $criteria->status,
            $criteria->cursor,
            $criteria->perPage + 1,
            $searchTerm,
        );
        $hasMore = count($rows) > $criteria->perPage;
        $visible = array_slice($rows, 0, $criteria->perPage);
        $last = $visible === [] ? null : $visible[array_key_last($visible)];
        $nextCursor = $hasMore && $last instanceof SubscriptionOverviewData
            ? $last->subscriptionId
            : null;

        return new DashboardSectionProjection('billingOverview', [
            'summary' => [
                ['key' => 'active', 'label' => 'Active subscriptions', 'value' => $summary->activeSubscriptions],
                ['key' => 'expiring', 'label' => 'Expiring in 30 days', 'value' => $summary->expiringSubscriptions],
                ['key' => 'expired', 'label' => 'Expired subscriptions', 'value' => $summary->expiredSubscriptions],
                [
                    'key' => 'revenue',
                    'label' => 'Revenue this year',
                    'value' => self::money($summary->annualRevenueMinor, $summary->currency),
                ],
            ],
            'paymentStatus' => [
                ['key' => 'pending', 'label' => 'Pending', 'value' => $summary->pendingPayments],
                ['key' => 'succeeded', 'label' => 'Succeeded', 'value' => $summary->succeededPayments],
                ['key' => 'failed', 'label' => 'Failed / closed', 'value' => $summary->failedPayments],
            ],
            'health' => [
                'status' => $summary->openReconciliationCases === 0 ? 'healthy' : 'attention_required',
                'label' => $summary->openReconciliationCases === 0 ? 'Healthy' : 'Attention required',
                'openReconciliationCases' => $summary->openReconciliationCases,
                'description' => $summary->openReconciliationCases === 0
                    ? 'No open payment reconciliation cases.'
                    : $summary->openReconciliationCases.' open payment reconciliation case(s).',
            ],
            'recentPayments' => array_map(static fn (RecentPaymentData $payment): array => [
                'id' => $payment->paymentId,
                'reference' => self::reference('PAY', $payment->paymentId),
                'tenantId' => $payment->tenantId ?? 'Not assigned',
                'tenantReference' => self::reference('TEN', $payment->tenantId),
                'clinicName' => $payment->clinicName ?? 'Clinic not yet assigned',
                'amount' => self::money($payment->amountMinor, $payment->currency),
                'status' => $payment->status,
                'statusLabel' => ucwords(str_replace('_', ' ', $payment->status)),
                'lastChangedAt' => $payment->lastChangedAt,
            ], $summary->recentPayments),
            'subscriptions' => array_map(static fn (SubscriptionOverviewData $subscription): array => [
                'id' => $subscription->subscriptionId,
                'reference' => self::reference('SUB', $subscription->subscriptionId),
                'detailHref' => route('dashboard.billing.subscriptions.show', $subscription->subscriptionId),
                'tenantId' => $subscription->tenantId,
                'tenantReference' => self::reference('TEN', $subscription->tenantId),
                'clinicName' => $subscription->clinicName ?? 'Clinic account',
                'planId' => $subscription->planId,
                'planReference' => self::reference('PLN', $subscription->planId),
                'planName' => $subscription->planName ?? 'Plan',
                'billingCycleId' => $subscription->billingCycleId,
                'amount' => self::money($subscription->amountMinor, $subscription->currency),
                'startsOn' => $subscription->startsOn,
                'endsOn' => $subscription->endsOn,
                'status' => $subscription->status,
                'statusLabel' => ucwords(str_replace('_', ' ', $subscription->status)),
            ], $visible),
            'search' => [
                'action' => route('dashboard.billing'),
                'value' => $criteria->search,
                'placeholder' => 'Search clinic, plan or reference',
            ],
            'statusFilter' => ['value' => $criteria->status, 'options' => BillingOverviewCriteria::statusOptions()],
            'pagination' => [
                'hasMore' => $hasMore,
                'perPage' => $criteria->perPage,
                'nextHref' => $nextCursor === null ? null : route('dashboard.billing', array_filter([
                    'search' => $criteria->search,
                    'status' => $criteria->status,
                    'cursor' => $nextCursor,
                    'per_page' => $criteria->perPage,
                ], static fn (string|int|null $value): bool => $value !== null)),
            ],
        ]);
    }

    private static function money(int $minor, string $currency): string
    {
        return $currency.' '.number_format($minor / 100, 2, '.', ',');
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

    private static function searchTerm(?string $search): ?string
    {
        if ($search === null || trim($search) === '') {
            return null;
        }

        $value = trim($search);

        return preg_replace('/^(PAY|SUB|TEN|PLN)-/i', '', $value) ?: $value;
    }
}
