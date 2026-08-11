<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries;

use App\Modules\SubscriptionBilling\Contracts\BillingDocument\BillingDocumentData;
use App\Modules\SubscriptionBilling\Contracts\BillingDocument\BillingDocumentReadInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;
use UnexpectedValueException;

final readonly class PostgresBillingDocumentReadAdapter implements BillingDocumentReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function listForTenant(string $trustedTenantId): array
    {
        $subscriptionId = $this->connection->table('subscriptions')
            ->where('tenant_id', $trustedTenantId)
            ->value('id');

        return $subscriptionId === null ? [] : $this->listForSubscription((string) $subscriptionId);
    }

    public function listForSubscription(string $subscriptionId): array
    {
        $documents = [];
        $initial = $this->initialQuery()->where('subscription.id', $subscriptionId)->first();
        if ($initial !== null) {
            $documents[] = $this->map($initial, 'initial_activation');
        }

        foreach ($this->renewalQuery()->where('subscription.id', $subscriptionId)->get() as $renewal) {
            $documents[] = $this->map($renewal, 'subscription_renewal');
        }

        usort($documents, static fn (BillingDocumentData $left, BillingDocumentData $right): int => strcmp($right->issuedAt, $left->issuedAt) ?: strcmp($right->paymentId, $left->paymentId));

        return $documents;
    }

    public function detail(string $paymentId): ?BillingDocumentData
    {
        $initial = $this->initialQuery()->where('payment.id', $paymentId)->first();
        if ($initial !== null) {
            return $this->map($initial, 'initial_activation');
        }

        $renewal = $this->renewalQuery()->where('payment.id', $paymentId)->first();

        return $renewal === null ? null : $this->map($renewal, 'subscription_renewal');
    }

    public function detailForTenant(string $paymentId, string $trustedTenantId): ?BillingDocumentData
    {
        $document = $this->detail($paymentId);

        return $document?->tenantId === $trustedTenantId ? $document : null;
    }

    private function initialQuery(): Builder
    {
        return $this->connection->table('subscriptions as subscription')
            ->join('payments as payment', 'payment.id', '=', 'subscription.payment_id')
            ->leftJoin('clinic_registrations as registration', 'registration.id', '=', 'subscription.clinic_registration_id')
            ->select([
                'subscription.id as subscription_id',
                'subscription.tenant_id',
                'subscription.plan_id',
                'subscription.billing_cycle_id',
                'subscription.starts_on as period_starts_on',
                'subscription.ends_on as period_ends_on',
                'registration.clinic_name',
                'payment.id as payment_id',
                'payment.amount_minor',
                'payment.currency',
                'payment.status as payment_status',
                'payment.provider_key',
                'payment.provider_payment_reference',
                'payment.domain_created_at',
                'payment.domain_last_changed_at',
            ]);
    }

    private function renewalQuery(): Builder
    {
        return $this->connection->table('subscriptions as subscription')
            ->join('subscription_renewals as renewal', 'renewal.subscription_id', '=', 'subscription.id')
            ->join('payments as payment', 'payment.id', '=', 'renewal.payment_id')
            ->leftJoin('clinic_registrations as registration', 'registration.id', '=', 'subscription.clinic_registration_id')
            ->select([
                'subscription.id as subscription_id',
                'subscription.tenant_id',
                'renewal.plan_id',
                'renewal.billing_cycle_id',
                'renewal.starts_on as period_starts_on',
                'renewal.ends_on as period_ends_on',
                'registration.clinic_name',
                'payment.id as payment_id',
                'payment.amount_minor',
                'payment.currency',
                'payment.status as payment_status',
                'payment.provider_key',
                'payment.provider_payment_reference',
                'payment.domain_created_at',
                'payment.domain_last_changed_at',
            ]);
    }

    private function map(object $row, string $purpose): BillingDocumentData
    {
        if (! $row instanceof stdClass) {
            throw new UnexpectedValueException('Billing document query returned an invalid row.');
        }

        $issuedAt = (string) $row->domain_created_at;
        $suffix = strtoupper(substr(str_replace('-', '', (string) $row->payment_id), 0, 10));
        $date = preg_replace('/[^0-9]/', '', substr($issuedAt, 0, 10)) ?: 'UNDATED';
        $succeeded = (string) $row->payment_status === 'succeeded';

        return new BillingDocumentData(
            (string) $row->payment_id,
            (string) $row->subscription_id,
            (string) $row->tenant_id,
            is_string($row->clinic_name) && $row->clinic_name !== '' ? $row->clinic_name : 'Clinic account',
            sprintf('SYF-INV-%s-%s', $date, $suffix),
            $succeeded ? sprintf('SYF-RCP-%s-%s', $date, $suffix) : null,
            $purpose,
            $this->catalogueName('commercial_catalogue_plans', (string) $row->plan_id) ?? (string) $row->plan_id,
            $this->catalogueName('commercial_catalogue_billing_options', (string) $row->billing_cycle_id) ?? (string) $row->billing_cycle_id,
            substr((string) $row->period_starts_on, 0, 10),
            substr((string) $row->period_ends_on, 0, 10),
            (int) $row->amount_minor,
            (string) $row->currency,
            (string) $row->payment_status,
            $issuedAt,
            $succeeded ? (string) $row->domain_last_changed_at : null,
            $row->provider_key === null ? null : (string) $row->provider_key,
            $row->provider_payment_reference === null ? null : (string) $row->provider_payment_reference,
        );
    }

    private function catalogueName(string $table, string $lineageId): ?string
    {
        $name = $this->connection->table($table)
            ->whereRaw('CAST(id AS TEXT) = ?', [$lineageId])
            ->value('name');

        return $name === null ? null : (string) $name;
    }
}
