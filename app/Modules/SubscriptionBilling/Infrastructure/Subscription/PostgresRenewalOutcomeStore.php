<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Renewal\ApplyRenewalOutcomeCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOutcomeResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOutcomeStoreInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use stdClass;

final readonly class PostgresRenewalOutcomeStore implements RenewalOutcomeStoreInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function apply(ApplyRenewalOutcomeCommand $command): RenewalOutcomeResult
    {
        return $this->connection->transaction(function () use ($command): RenewalOutcomeResult {
            $existing = $this->connection->table('renewal_outcome_applications')
                ->where('source_event_id', $command->sourceEventId)->first();
            if ($existing instanceof stdClass) {
                return new RenewalOutcomeResult((string) $existing->result_code, (string) $existing->renewal_id);
            }
            $payment = $this->connection->table('payments')->where('id', $command->paymentId)->lockForUpdate()->first();
            if (! $payment instanceof stdClass || $payment->status !== $command->outcome) {
                return new RenewalOutcomeResult('payment_mismatch');
            }
            $renewal = $this->connection->table('subscription_renewals')
                ->where('payment_id', $command->paymentId)->lockForUpdate()->first();
            if (! $renewal instanceof stdClass) {
                return new RenewalOutcomeResult('renewal_missing');
            }
            $subscription = $this->connection->table('subscriptions')
                ->where('id', $renewal->subscription_id)->lockForUpdate()->first();
            if (! $subscription instanceof stdClass) {
                return new RenewalOutcomeResult('renewal_missing', (string) $renewal->id);
            }
            $now = $command->occurredAt->format('Y-m-d H:i:s.uP');
            $result = $this->applyOutcome($command, $renewal, $subscription, $now);
            $this->connection->table('renewal_outcome_applications')->insert([
                'id' => (string) Str::uuid(), 'source_event_id' => $command->sourceEventId,
                'payment_id' => $command->paymentId, 'renewal_id' => $renewal->id,
                'outcome' => $command->outcome, 'result_code' => $result->code,
                'applied_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);

            return $result;
        });
    }

    private function applyOutcome(ApplyRenewalOutcomeCommand $command, stdClass $renewal, stdClass $subscription, string $now): RenewalOutcomeResult
    {
        if ($renewal->status === $command->outcome) {
            return new RenewalOutcomeResult('already_reflected', (string) $renewal->id);
        }
        if (! in_array($renewal->status, ['payment_pending', 'action_required'], true)) {
            return new RenewalOutcomeResult('invalid_state', (string) $renewal->id);
        }
        if ($command->outcome === 'succeeded') {
            $expectedStart = (new DateTimeImmutable((string) $subscription->ends_on))->modify('+1 day')->format('Y-m-d');
            if ($expectedStart !== (string) $renewal->starts_on) {
                return $this->reconciliation($command, $renewal, $subscription, $now);
            }
            $this->connection->table('subscriptions')->where('id', $subscription->id)->update([
                'ends_on' => $renewal->ends_on, 'status' => 'active',
                'last_changed_at' => $now, 'version' => (int) $subscription->version + 1, 'updated_at' => $now,
            ]);
            $this->finish($command, $renewal, $subscription, 'succeeded', 'subscription_renewed', 'SubscriptionRenewed', $now);

            return new RenewalOutcomeResult('applied', (string) $renewal->id);
        }
        $status = $command->outcome === 'expired' ? 'expired' : 'failed';
        $event = $status === 'expired' ? 'renewal_expired' : 'renewal_failed';
        $outbox = $status === 'expired' ? 'SubscriptionRenewalExpired' : 'SubscriptionRenewalFailed';
        $this->finish($command, $renewal, $subscription, $status, $event, $outbox, $now);
        if ($renewal->mode === 'auto') {
            $this->connection->table('subscriptions')->where('id', $subscription->id)->update([
                'auto_renew_status' => 'failed', 'auto_renew_changed_at' => $now,
                'last_changed_at' => $now, 'version' => (int) $subscription->version + 1, 'updated_at' => $now,
            ]);
        }

        return new RenewalOutcomeResult('applied', (string) $renewal->id);
    }

    private function reconciliation(ApplyRenewalOutcomeCommand $command, stdClass $renewal, stdClass $subscription, string $now): RenewalOutcomeResult
    {
        $this->finish(
            $command,
            $renewal,
            $subscription,
            'reconciliation_required',
            'renewal_reconciliation_required',
            'SubscriptionRenewalReconciliationRequired',
            $now,
        );

        return new RenewalOutcomeResult('reconciliation_required', (string) $renewal->id);
    }

    private function finish(ApplyRenewalOutcomeCommand $command, stdClass $renewal, stdClass $subscription, string $status, string $timeline, string $outbox, string $now): void
    {
        $this->connection->table('subscription_renewals')->where('id', $renewal->id)->update([
            'status' => $status, 'last_changed_at' => $now,
            'version' => (int) $renewal->version + 1, 'updated_at' => $now,
        ]);
        $this->connection->table('subscription_timeline_entries')->insert([
            'id' => (string) Str::uuid(), 'subscription_id' => $subscription->id,
            'renewal_id' => $renewal->id, 'payment_id' => $command->paymentId,
            'event_type' => $timeline, 'correlation_id' => $command->correlationId, 'occurred_at' => $now,
        ]);
        $this->connection->table('subscription_renewal_outbox')->insert([
            'id' => (string) Str::uuid(), 'event_type' => $outbox, 'event_version' => 1,
            'subscription_id' => $subscription->id, 'renewal_id' => $renewal->id, 'payment_id' => $command->paymentId,
            'payload' => json_encode(['subscription_id' => $subscription->id, 'renewal_id' => $renewal->id, 'payment_id' => $command->paymentId], JSON_THROW_ON_ERROR),
            'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
