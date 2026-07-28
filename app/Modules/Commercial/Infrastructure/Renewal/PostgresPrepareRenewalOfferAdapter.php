<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\Renewal;

use App\Modules\Commercial\Contracts\Renewal\PreparedRenewalOffer;
use App\Modules\Commercial\Contracts\Renewal\PrepareRenewalOfferInput;
use App\Modules\Commercial\Contracts\Renewal\PrepareRenewalOfferInterface;
use App\Modules\Commercial\Contracts\Renewal\RenewalUnavailable;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCommercialContextReadInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use stdClass;

final readonly class PostgresPrepareRenewalOfferAdapter implements PrepareRenewalOfferInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private RenewalCommercialContextReadInterface $subscriptions,
        private int $ttlMinutes,
    ) {}

    public function prepare(PrepareRenewalOfferInput $input): PreparedRenewalOffer|RenewalUnavailable
    {
        $existing = $this->connection->table('commercial_offers')
            ->where('purpose', 'subscription_renewal')
            ->where('subscription_id', $input->subscriptionId)
            ->where('request_idempotency_key', $input->idempotencyKey)
            ->first();
        if ($existing !== null) {
            return $this->map($existing);
        }

        $context = $this->subscriptions->currentForRenewal($input->subscriptionId);
        if ($context === null) {
            return new RenewalUnavailable('subscription_not_found');
        }
        if ($context->status !== 'renewal_due') {
            return new RenewalUnavailable('subscription_not_eligible');
        }

        $offerings = $this->connection->table('commercial_catalogue_plan_offerings')
            ->where('plan_id', $context->planId)
            ->where('billing_option_id', $context->billingCycleId)
            ->where('status', 'active')
            ->whereDate('effective_start', '<=', $input->occurredAt->format('Y-m-d'))
            ->where(static function ($query) use ($input): void {
                $query->whereNull('effective_end')->orWhereDate('effective_end', '>=', $input->occurredAt->format('Y-m-d'));
            })
            ->limit(2)
            ->get();
        if ($offerings->count() !== 1) {
            return new RenewalUnavailable($offerings->isEmpty() ? 'offering_not_found' : 'offering_ambiguous');
        }

        $offering = $offerings->first();
        if (! $offering instanceof stdClass) {
            return new RenewalUnavailable('offering_not_found');
        }
        $startsOn = (new DateTimeImmutable($context->endsOn))->modify('+1 day');
        $endsOn = $startsOn->modify('+1 year')->modify('-1 day');
        $expiresAt = $input->occurredAt->modify('+'.$this->ttlMinutes.' minutes');
        $offerId = (string) Str::uuid();

        $this->connection->transaction(function () use ($input, $context, $offering, $startsOn, $endsOn, $expiresAt, $offerId): void {
            $now = $input->occurredAt->format('Y-m-d H:i:s.uP');
            $this->connection->table('commercial_offers')->insert([
                'id' => $offerId,
                'platform_identity_id' => $input->initiatingActorId,
                'clinic_registration_id' => $context->clinicRegistrationId,
                'tenant_id' => $context->tenantId,
                'status' => 'prepared',
                'plan_offering_id' => (string) $offering->id,
                'plan_id' => $context->planId,
                'billing_cycle_id' => $context->billingCycleId,
                'billing_period_start' => $startsOn->format('Y-m-d'),
                'billing_period_end' => $endsOn->format('Y-m-d'),
                'offering_configuration_version' => (string) $offering->configuration_version,
                'capability_configuration_reference' => (string) $offering->capability_configuration_reference,
                'subtotal_amount_minor' => (int) $offering->amount_minor,
                'total_amount_minor' => (int) $offering->amount_minor,
                'currency' => (string) $offering->currency_code,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s.uP'),
                'correlation_id' => $input->correlationId,
                'version' => 1,
                'owner_kind' => 'system_commercial',
                'purpose' => 'subscription_renewal',
                'subscription_id' => $input->subscriptionId,
                'request_idempotency_key' => $input->idempotencyKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->connection->table('commercial_offer_line_items')->insert([
                'id' => (string) Str::uuid(),
                'commercial_offer_id' => $offerId,
                'item_type' => 'plan_offering',
                'item_reference' => (string) $offering->id,
                'description' => 'Subscription renewal',
                'quantity' => 1,
                'unit_amount_minor' => (int) $offering->amount_minor,
                'total_amount_minor' => (int) $offering->amount_minor,
                'currency' => (string) $offering->currency_code,
                'catalogue_snapshot_reference' => (string) $offering->configuration_version,
                'position' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return new PreparedRenewalOffer(
            $offerId, $input->subscriptionId, $context->planId, $context->billingCycleId,
            (int) $offering->amount_minor, (string) $offering->currency_code,
            $expiresAt->format(DATE_ATOM), $startsOn->format('Y-m-d'), $endsOn->format('Y-m-d'),
            (string) $offering->configuration_version,
        );
    }

    private function map(stdClass $row): PreparedRenewalOffer
    {
        return new PreparedRenewalOffer(
            (string) $row->id, (string) $row->subscription_id, (string) $row->plan_id,
            (string) $row->billing_cycle_id, (int) $row->total_amount_minor, (string) $row->currency,
            (string) $row->expires_at, (string) $row->billing_period_start,
            (string) $row->billing_period_end, (string) $row->offering_configuration_version,
        );
    }
}
