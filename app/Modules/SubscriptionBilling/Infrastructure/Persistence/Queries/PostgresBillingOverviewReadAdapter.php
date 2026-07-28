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

        $recentPayments = $this->connection->table('payments')
            ->select(['id', 'tenant_id', 'amount_minor', 'currency', 'status', 'domain_last_changed_at'])
            ->orderByDesc('domain_last_changed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(static fn (object $row): RecentPaymentData => new RecentPaymentData(
                (string) $row->id,
                $row->tenant_id === null ? null : (string) $row->tenant_id,
                (int) $row->amount_minor,
                (string) $row->currency,
                (string) $row->status,
                (string) $row->domain_last_changed_at,
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
        $query = $this->connection->table('subscriptions')
            ->select([
                'id', 'tenant_id', 'plan_id', 'billing_cycle_id', 'amount_minor',
                'currency', 'starts_on', 'ends_on', 'status',
            ]);

        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($cursor !== null) {
            $query->where('id', '>', $cursor);
        }
        if ($search !== null) {
            $query->where(static function ($query) use ($search): void {
                $query->where('id', 'ilike', '%'.$search.'%')
                    ->orWhere('tenant_id', 'ilike', '%'.$search.'%');
            });
        }

        return array_values($query->orderBy('id')->limit($limit)->get()
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
            ))->all());
    }
}
