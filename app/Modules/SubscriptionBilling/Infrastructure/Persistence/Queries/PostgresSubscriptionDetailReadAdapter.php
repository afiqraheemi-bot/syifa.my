<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries;

use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCommercialContextData;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCommercialContextReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\ClinicOwnerSubscriptionDetailData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\ClinicOwnerSubscriptionDetailReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\PaymentHistoryReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionDetailData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionDetailReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionPaymentData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionTimelineData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionTimelineReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresSubscriptionDetailReadAdapter implements ClinicOwnerSubscriptionDetailReadInterface, PaymentHistoryReadInterface, RenewalCommercialContextReadInterface, SubscriptionDetailReadInterface, SubscriptionTimelineReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function detail(string $subscriptionId): ?SubscriptionDetailData
    {
        $row = $this->connection->table('subscriptions')->where('id', $subscriptionId)->first();
        if ($row === null) {
            return null;
        }
        $renewalCheckoutId = null;
        if ((string) $row->status === 'renewal_due') {
            $eligibleRenewal = $this->connection->table('subscription_renewals as renewal')
                ->join('commercial_offers as offer', 'offer.id', '=', 'renewal.commercial_offer_id')
                ->where('renewal.subscription_id', $subscriptionId)
                ->where('renewal.status', 'requested')
                ->whereNotNull('renewal.payment_id')
                ->where('offer.expires_at', '>', now())
                ->orderByDesc('renewal.requested_at')
                ->select('renewal.id')
                ->first();
            $renewalCheckoutId = $eligibleRenewal === null ? null : (string) $eligibleRenewal->id;
        }

        return new SubscriptionDetailData(
            (string) $row->id,
            (string) $row->tenant_id,
            (string) $row->plan_id,
            (string) $row->billing_cycle_id,
            (int) $row->amount_minor,
            (string) $row->currency,
            (string) $row->starts_on,
            (string) $row->ends_on,
            (string) $row->status,
            (string) $row->status === 'renewal_due' ? 'due' : 'not_due',
            (string) $row->auto_renew_status,
            (int) $row->version,
            $renewalCheckoutId,
        );
    }

    public function detailForTenant(string $trustedTenantId): ?ClinicOwnerSubscriptionDetailData
    {
        $row = $this->connection->table('subscriptions as subscription')
            ->where('subscription.tenant_id', $trustedTenantId)
            ->select([
                'subscription.id',
                'subscription.payment_id',
                'subscription.plan_id',
                'subscription.billing_cycle_id',
                'subscription.starts_on',
                'subscription.ends_on',
                'subscription.status',
            ])
            ->first();
        if ($row === null) {
            return null;
        }

        $eligible = (string) $row->status === 'renewal_due'
            && $this->eligibleRenewalId((string) $row->id) !== null;

        return new ClinicOwnerSubscriptionDetailData(
            $this->catalogueName('commercial_catalogue_plans', (string) $row->plan_id)
                ?? (string) $row->plan_id,
            $this->catalogueName('commercial_catalogue_billing_options', (string) $row->billing_cycle_id)
                ?? (string) $row->billing_cycle_id,
            substr((string) $row->starts_on, 0, 10),
            substr((string) $row->ends_on, 0, 10),
            (string) $row->status,
            $eligible ? 'eligible' : 'not_eligible',
            $this->latestPaymentStatus((string) $row->id, (string) $row->payment_id),
            $eligible,
        );
    }

    public function list(string $subscriptionId, ?string $cursor, int $limit): array
    {
        $query = $this->connection->table('subscription_timeline_entries')->where('subscription_id', $subscriptionId);
        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        return array_values($query->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit)->get()
            ->map(static fn (object $row): SubscriptionTimelineData => new SubscriptionTimelineData(
                (string) $row->id,
                (string) $row->event_type,
                (string) $row->occurred_at,
            ))->all());
    }

    public function listForSubscription(string $subscriptionId, ?string $cursor, int $limit): array
    {
        $query = $this->connection->table('subscriptions')
            ->join('payments', 'payments.id', '=', 'subscriptions.payment_id')
            ->where('subscriptions.id', $subscriptionId)
            ->select([
                'payments.id', 'payments.amount_minor', 'payments.currency',
                'payments.status', 'payments.domain_last_changed_at',
            ]);
        if ($cursor !== null) {
            $query->where('payments.id', '<', $cursor);
        }

        return array_values($query->orderByDesc('payments.id')->limit($limit)->get()
            ->map(static fn (object $row): SubscriptionPaymentData => new SubscriptionPaymentData(
                (string) $row->id,
                'initial_activation',
                (int) $row->amount_minor,
                (string) $row->currency,
                (string) $row->status,
                (string) $row->domain_last_changed_at,
            ))->all());
    }

    public function currentForRenewal(string $subscriptionId): ?RenewalCommercialContextData
    {
        $row = $this->connection->table('subscriptions')->where('id', $subscriptionId)->first();
        if ($row === null) {
            return null;
        }

        return new RenewalCommercialContextData(
            (string) $row->id,
            (string) $row->tenant_id,
            (string) $row->clinic_registration_id,
            (string) $row->plan_id,
            (string) $row->billing_cycle_id,
            (string) $row->ends_on,
            (string) $row->status,
            (int) $row->version,
        );
    }

    private function eligibleRenewalId(string $subscriptionId): ?string
    {
        $row = $this->connection->table('subscription_renewals as renewal')
            ->join('commercial_offers as offer', 'offer.id', '=', 'renewal.commercial_offer_id')
            ->where('renewal.subscription_id', $subscriptionId)
            ->where('renewal.status', 'requested')
            ->whereNotNull('renewal.payment_id')
            ->where('offer.expires_at', '>', now())
            ->orderByDesc('renewal.requested_at')
            ->select('renewal.id')
            ->first();

        return $row === null ? null : (string) $row->id;
    }

    private function latestPaymentStatus(string $subscriptionId, string $initialPaymentId): ?string
    {
        $renewalPayment = $this->connection->table('subscription_renewals as renewal')
            ->join('payments as payment', 'payment.id', '=', 'renewal.payment_id')
            ->where('renewal.subscription_id', $subscriptionId)
            ->orderByDesc('renewal.requested_at')
            ->select('payment.status')
            ->first();
        if ($renewalPayment !== null) {
            return (string) $renewalPayment->status;
        }

        $initialPayment = $this->connection->table('payments')
            ->where('id', $initialPaymentId)
            ->value('status');

        return $initialPayment === null ? null : (string) $initialPayment;
    }

    private function catalogueName(string $table, string $lineageId): ?string
    {
        $name = $this->connection->table($table)
            ->whereRaw('CAST(id AS TEXT) = ?', [$lineageId])
            ->value('name');

        return $name === null ? null : (string) $name;
    }
}
