<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Renewal\BeginRenewalCheckoutCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RedirectAction;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutState;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutStoreInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use LogicException;
use stdClass;

final readonly class PostgresRenewalCheckoutStore implements RenewalCheckoutStoreInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function begin(BeginRenewalCheckoutCommand $command): RenewalCheckoutState
    {
        return $this->connection->transaction(function () use ($command): RenewalCheckoutState {
            $existing = $this->connection->table('renewal_checkout_applications')
                ->where('idempotency_key', $command->idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof stdClass) {
                return $this->state($existing);
            }
            $renewal = $this->connection->table('subscription_renewals')
                ->where('id', $command->renewalId)->lockForUpdate()->first();
            if (! $renewal instanceof stdClass) {
                throw new LogicException('Subscription Renewal was not found.');
            }
            if ($renewal->payment_id !== null && $renewal->payment_id !== $command->paymentId) {
                throw new LogicException('Subscription Renewal Payment lineage cannot be changed.');
            }
            $now = $command->occurredAt->format('Y-m-d H:i:s.uP');
            $applicationId = (string) Str::uuid();
            $this->connection->table('subscription_renewals')->where('id', $command->renewalId)
                ->update(['payment_id' => $command->paymentId, 'updated_at' => $now]);
            $this->connection->table('renewal_checkout_applications')->insert([
                'id' => $applicationId,
                'renewal_id' => $command->renewalId,
                'payment_id' => $command->paymentId,
                'idempotency_key' => $command->idempotencyKey,
                'stage' => 'session_pending',
                'commercial_offer_valid_until' => $command->commercialOfferValidUntil->format('Y-m-d H:i:s.uP'),
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return new RenewalCheckoutState($applicationId, $command->renewalId, $command->paymentId, 'session_pending');
        });
    }

    public function sessionReady(string $applicationId, string $paymentId, PaymentSession $session, string $correlationId): RenewalCheckoutState
    {
        return $this->connection->transaction(function () use ($applicationId, $paymentId, $session, $correlationId): RenewalCheckoutState {
            $row = $this->connection->table('renewal_checkout_applications')->where('id', $applicationId)->lockForUpdate()->first();
            if (! $row instanceof stdClass || $row->payment_id !== $paymentId) {
                throw new LogicException('Renewal checkout Payment lineage does not match.');
            }
            if ($row->stage === 'session_ready') {
                return $this->state($row);
            }
            $renewal = $this->connection->table('subscription_renewals')->where('id', $row->renewal_id)->lockForUpdate()->first();
            if (! $renewal instanceof stdClass || $renewal->payment_id !== $paymentId) {
                throw new LogicException('Subscription Renewal Payment lineage does not match.');
            }
            $now = (new DateTimeImmutable)->format('Y-m-d H:i:s.uP');
            $this->connection->table('renewal_checkout_applications')->where('id', $applicationId)->update([
                'stage' => 'session_ready',
                'session_id' => $session->sessionId,
                'redirect_destination' => $session->redirectAction->destination,
                'session_expires_at' => $session->expiresAt?->format('Y-m-d H:i:s.uP'),
                'expiry_authority' => $session->expiryAuthority->value,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);
            $this->connection->table('subscription_renewals')->where('id', $row->renewal_id)->update([
                'status' => 'payment_pending', 'last_changed_at' => $now,
                'version' => (int) $renewal->version + 1, 'updated_at' => $now,
            ]);
            $this->timeline((string) $renewal->subscription_id, (string) $row->renewal_id, $paymentId, 'renewal_payment_pending', $correlationId, $now);
            $this->outbox((string) $renewal->subscription_id, (string) $row->renewal_id, $paymentId, 'RenewalPaymentPending', $now);

            $updated = $this->connection->table('renewal_checkout_applications')->where('id', $applicationId)->first();

            return $this->state($updated);
        });
    }

    public function fail(string $applicationId, string $safeFailureCode, string $correlationId): RenewalCheckoutState
    {
        return $this->connection->transaction(function () use ($applicationId, $safeFailureCode): RenewalCheckoutState {
            $row = $this->connection->table('renewal_checkout_applications')->where('id', $applicationId)->lockForUpdate()->first();
            if (! $row instanceof stdClass) {
                throw new LogicException('Renewal checkout was not found.');
            }
            if ($row->stage !== 'session_ready') {
                $this->connection->table('renewal_checkout_applications')->where('id', $applicationId)->update([
                    'stage' => 'failed', 'safe_failure_code' => $safeFailureCode,
                    'completed_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $this->state($this->connection->table('renewal_checkout_applications')->where('id', $applicationId)->first());
        });
    }

    private function state(?stdClass $row): RenewalCheckoutState
    {
        if (! $row instanceof stdClass) {
            throw new LogicException('Renewal checkout storage state is missing.');
        }
        $session = $row->stage === 'session_ready'
            ? new PaymentSession(
                (string) $row->session_id,
                new RedirectAction((string) $row->redirect_destination),
                new DateTimeImmutable((string) $row->session_expires_at),
                ExpiryAuthority::from((string) $row->expiry_authority),
            )
            : null;

        return new RenewalCheckoutState(
            (string) $row->id,
            (string) $row->renewal_id,
            (string) $row->payment_id,
            (string) $row->stage,
            $session,
            $row->safe_failure_code === null ? null : (string) $row->safe_failure_code,
        );
    }

    private function timeline(string $subscriptionId, string $renewalId, string $paymentId, string $type, string $correlationId, string $now): void
    {
        $this->connection->table('subscription_timeline_entries')->insert([
            'id' => (string) Str::uuid(), 'subscription_id' => $subscriptionId,
            'renewal_id' => $renewalId, 'payment_id' => $paymentId, 'event_type' => $type,
            'correlation_id' => $correlationId, 'occurred_at' => $now,
        ]);
    }

    private function outbox(string $subscriptionId, string $renewalId, string $paymentId, string $type, string $now): void
    {
        $this->connection->table('subscription_renewal_outbox')->insert([
            'id' => (string) Str::uuid(), 'event_type' => $type, 'event_version' => 1,
            'subscription_id' => $subscriptionId, 'renewal_id' => $renewalId, 'payment_id' => $paymentId,
            'payload' => json_encode(['subscription_id' => $subscriptionId, 'renewal_id' => $renewalId, 'payment_id' => $paymentId], JSON_THROW_ON_ERROR),
            'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
