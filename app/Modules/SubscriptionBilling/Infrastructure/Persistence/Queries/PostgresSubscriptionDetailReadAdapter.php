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
        $row = $this->connection->table('subscriptions as subscription')
            ->leftJoin('websites as website', 'website.tenant_id', '=', 'subscription.tenant_id')
            ->leftJoin('clinic_registrations as registration', 'registration.id', '=', 'subscription.clinic_registration_id')
            ->leftJoin('commercial_catalogue_plans as plan', static function ($join): void {
                $join->whereRaw('CAST(plan.id AS TEXT) = subscription.plan_id');
            })
            ->where('subscription.id', $subscriptionId)
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
                'subscription.auto_renew_status',
                'subscription.version',
                'plan.name as plan_name',
            ])
            ->selectRaw('COALESCE(website.clinic_name, registration.clinic_name) AS clinic_name')
            ->first();
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
            is_string($row->clinic_name) && $row->clinic_name !== '' ? $row->clinic_name : null,
            is_string($row->plan_name) && $row->plan_name !== '' ? $row->plan_name : null,
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
        $payments = [];
        $initial = $this->connection->table('subscriptions')
            ->join('payments', 'payments.id', '=', 'subscriptions.payment_id')
            ->where('subscriptions.id', $subscriptionId)
            ->select([
                'payments.id', 'payments.amount_minor', 'payments.currency',
                'payments.status', 'payments.domain_last_changed_at',
            ])->first();
        if ($initial !== null) {
            $payments[] = new SubscriptionPaymentData(
                (string) $initial->id,
                'initial_activation',
                (int) $initial->amount_minor,
                (string) $initial->currency,
                (string) $initial->status,
                (string) $initial->domain_last_changed_at,
            );
        }

        foreach ($this->connection->table('subscription_renewals as renewal')
            ->join('payments as payment', 'payment.id', '=', 'renewal.payment_id')
            ->where('renewal.subscription_id', $subscriptionId)
            ->select([
                'payment.id', 'payment.amount_minor', 'payment.currency',
                'payment.status', 'payment.domain_last_changed_at',
            ])->get() as $renewal) {
            $payments[] = new SubscriptionPaymentData(
                (string) $renewal->id,
                'subscription_renewal',
                (int) $renewal->amount_minor,
                (string) $renewal->currency,
                (string) $renewal->status,
                (string) $renewal->domain_last_changed_at,
            );
        }

        $payments = array_values(array_filter(
            $payments,
            static fn (SubscriptionPaymentData $payment): bool => $cursor === null || strcmp($payment->paymentId, $cursor) < 0,
        ));
        usort($payments, static fn (SubscriptionPaymentData $left, SubscriptionPaymentData $right): int => strcmp($right->occurredAt, $left->occurredAt) ?: strcmp($right->paymentId, $left->paymentId));

        return array_slice($payments, 0, $limit);
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
