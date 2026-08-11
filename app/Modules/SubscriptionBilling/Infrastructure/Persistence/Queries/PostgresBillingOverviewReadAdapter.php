<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries;

use App\Modules\SubscriptionBilling\Contracts\BillingOverview\BillingOverviewData;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\BillingOverviewReadInterface;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\RecentPaymentData;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\SubscriptionOverviewData;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresBillingOverviewReadAdapter implements BillingOverviewReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function summary(string $asOfDate): BillingOverviewData
    {
        $endOfWindow = (new \DateTimeImmutable($asOfDate))->modify('+30 days')->format('Y-m-d');
        $yearStart = substr($asOfDate, 0, 4).'-01-01';
        $subscriptionCounts = $this->connection->table('subscriptions')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'active') AS active")
            ->selectRaw(
                "COUNT(*) FILTER (WHERE status = 'active' AND ends_on >= ? AND ends_on <= ?) AS expiring",
                [$asOfDate, $endOfWindow],
            )
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'expired') AS expired")
            ->first();
        $paymentCounts = $this->connection->table('payments')
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('draft','pending','action_required')) AS pending")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'succeeded') AS succeeded")
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('failed','cancelled','expired')) AS failed")
            ->selectRaw(
                "COALESCE(SUM(amount_minor) FILTER (WHERE status = 'succeeded' AND domain_last_changed_at >= ?), 0) AS annual_revenue",
                [$yearStart.' 00:00:00+00'],
            )
            ->first();

        $recentPayments = $this->connection->table('payments as payment')
            ->leftJoin('websites as website', 'website.tenant_id', '=', 'payment.tenant_id')
            ->leftJoin('clinic_registrations as registration', 'registration.id', '=', 'payment.clinic_registration_id')
            ->select([
                'payment.id',
                'payment.tenant_id',
                'payment.amount_minor',
                'payment.currency',
                'payment.status',
                'payment.domain_last_changed_at',
            ])
            ->selectRaw('COALESCE(website.clinic_name, registration.clinic_name) AS clinic_name')
            ->orderByDesc('payment.domain_last_changed_at')
            ->orderByDesc('payment.id')
            ->limit(5)
            ->get()
            ->map(static fn (object $row): RecentPaymentData => new RecentPaymentData(
                (string) $row->id,
                $row->tenant_id === null ? null : (string) $row->tenant_id,
                (int) $row->amount_minor,
                (string) $row->currency,
                (string) $row->status,
                (string) $row->domain_last_changed_at,
                is_string($row->clinic_name) && $row->clinic_name !== '' ? $row->clinic_name : null,
            ))
            ->all();

        return new BillingOverviewData(
            (int) ($subscriptionCounts->active ?? 0),
            (int) ($subscriptionCounts->expiring ?? 0),
            (int) ($subscriptionCounts->expired ?? 0),
            (int) ($paymentCounts->annual_revenue ?? 0),
            'MYR',
            array_values($recentPayments),
            (int) ($paymentCounts->pending ?? 0),
            (int) ($paymentCounts->succeeded ?? 0),
            (int) ($paymentCounts->failed ?? 0),
            $this->connection->table('payment_reconciliation_cases')->where('status', 'open')->count(),
        );
    }

    public function subscriptions(?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        $query = $this->connection->table('subscriptions as subscription')
            ->leftJoin('websites as website', 'website.tenant_id', '=', 'subscription.tenant_id')
            ->leftJoin('clinic_registrations as registration', 'registration.id', '=', 'subscription.clinic_registration_id')
            ->leftJoin('commercial_catalogue_plans as plan', static function ($join): void {
                $join->whereRaw('CAST(plan.id AS TEXT) = subscription.plan_id');
            })
            ->select([
                'subscription.id',
                'subscription.tenant_id',
                'subscription.plan_id',
                'subscription.billing_cycle_id',
                'subscription.amount_minor',
                'subscription.currency',
                'subscription.starts_on',
                'subscription.ends_on',
                'subscription.status',
                'plan.name as plan_name',
            ])
            ->selectRaw('COALESCE(website.clinic_name, registration.clinic_name) AS clinic_name');

        if ($status !== null) {
            $query->where('subscription.status', $status);
        }
        if ($cursor !== null) {
            $query->where('subscription.id', '>', $cursor);
        }
        if ($search !== null) {
            $query->where(static function ($query) use ($search): void {
                $query->whereRaw('CAST(subscription.id AS TEXT) ILIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('CAST(subscription.tenant_id AS TEXT) ILIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('CAST(subscription.plan_id AS TEXT) ILIKE ?', ['%'.$search.'%'])
                    ->orWhere('website.clinic_name', 'ilike', '%'.$search.'%')
                    ->orWhere('registration.clinic_name', 'ilike', '%'.$search.'%')
                    ->orWhere('plan.name', 'ilike', '%'.$search.'%');
            });
        }

        return array_values($query->orderBy('subscription.id')->limit($limit)->get()
            ->map(static fn (object $row): SubscriptionOverviewData => new SubscriptionOverviewData(
                (string) $row->id,
                (string) $row->tenant_id,
                (string) $row->plan_id,
                (string) $row->billing_cycle_id,
                (int) $row->amount_minor,
                (string) $row->currency,
                (string) $row->starts_on,
                (string) $row->ends_on,
                (string) $row->status,
                is_string($row->clinic_name) && $row->clinic_name !== '' ? $row->clinic_name : null,
                is_string($row->plan_name) && $row->plan_name !== '' ? $row->plan_name : null,
            ))->all());
    }
}
