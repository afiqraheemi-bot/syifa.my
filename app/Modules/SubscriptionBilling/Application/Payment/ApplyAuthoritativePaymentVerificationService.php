<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\TransientPaymentApplicationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentApplicationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentApplicationTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentOutboxRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentReconciliationCaseRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplicationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplicationStatus;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationOutcome;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceipt;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\PaymentAttempt;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentStatus;
use DateTimeImmutable;

final readonly class ApplyAuthoritativePaymentVerificationService
{
    public function __construct(
        private PaymentVerificationApplicationRepositoryInterface $applications,
        private ProviderWebhookReceiptRepositoryInterface $receipts,
        private PaymentRepositoryInterface $payments,
        private PaymentReconciliationCaseRepositoryInterface $reconciliations,
        private PaymentOutboxRepositoryInterface $outbox,
        private PaymentVerificationFinancialAuditTrail $audit,
        private PaymentApplicationTransactionInterface $transactions,
        private PaymentApplicationJobDispatcherInterface $jobs,
        private PaymentApplicationRetryPolicy $retry,
    ) {}

    public function execute(string $applicationId): void
    {
        $now = new DateTimeImmutable;
        $claim = $this->applications->claim($applicationId, $now, $this->retry->leaseSeconds);
        if ($claim === null || $claim->claimToken === null) {
            return;
        }

        try {
            $this->transactions->run(function () use ($claim, $now): void {
                $receipt = $this->receipts->findById($claim->receiptId);
                if ($receipt === null || ! $this->completeEvidence($receipt)) {
                    $this->finish($claim->id, $claim->claimToken, PaymentVerificationApplicationStatus::Quarantined, PaymentVerificationApplicationResultCode::InvalidEvidence, $now);

                    return;
                }
                $paymentId = $receipt->resolvedPaymentId;
                $verificationOutcome = $receipt->verificationOutcome;
                if ($paymentId === null || $verificationOutcome === null) {
                    $this->finish($claim->id, $claim->claimToken, PaymentVerificationApplicationStatus::Quarantined, PaymentVerificationApplicationResultCode::InvalidEvidence, $now);

                    return;
                }
                $payment = $this->payments->find(new PaymentId($paymentId));
                if ($payment === null) {
                    $this->finish($claim->id, $claim->claimToken, PaymentVerificationApplicationStatus::Quarantined, PaymentVerificationApplicationResultCode::PaymentMissing, $now);

                    return;
                }
                [$attempt, $current] = $this->locateAttempt($payment, $receipt);
                if ($attempt === null) {
                    $this->finish($claim->id, $claim->claimToken, PaymentVerificationApplicationStatus::Quarantined, PaymentVerificationApplicationResultCode::AttemptMismatch, $now);

                    return;
                }
                $outcome = ProviderPaymentVerificationOutcome::from($verificationOutcome);
                if (! $current) {
                    $outcome === ProviderPaymentVerificationOutcome::Succeeded
                        ? $this->reconcile($claim->id, $claim->claimToken, $receipt, $payment, $attempt, 'historical_success', $now)
                        : $this->ignore($claim->id, $claim->claimToken, $receipt, $payment, PaymentVerificationApplicationResultCode::Superseded, $now);

                    return;
                }
                $this->applyCurrent($claim->id, $claim->claimToken, $receipt, $payment, $attempt, $outcome, $now);
            });
        } catch (TransientPaymentApplicationException) {
            $now = new DateTimeImmutable;
            if ($claim->attemptCount >= $this->retry->maxAttempts) {
                $this->finish($claim->id, $claim->claimToken, PaymentVerificationApplicationStatus::Exhausted, PaymentVerificationApplicationResultCode::Regressive, $now);

                return;
            }
            $delay = $this->retry->delay($claim->attemptCount);
            if ($this->applications->complete($claim->id, $claim->claimToken, PaymentVerificationApplicationStatus::RetryPending, PaymentVerificationApplicationResultCode::Regressive, $now, $now->modify('+'.$delay.' seconds'))) {
                $this->jobs->dispatch($claim->id, $delay);
            }
        }
    }

    private function applyCurrent(string $id, string $token, ProviderWebhookReceipt $receipt, Payment $payment, PaymentAttempt $attempt, ProviderPaymentVerificationOutcome $outcome, DateTimeImmutable $now): void
    {
        if ($outcome === ProviderPaymentVerificationOutcome::Succeeded && in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Cancelled], true)) {
            $this->reconcile($id, $token, $receipt, $payment, $attempt, 'success_after_'.$payment->status->value, $now);

            return;
        }
        $event = match ($outcome) {
            ProviderPaymentVerificationOutcome::Succeeded => $this->transition($payment, [PaymentStatus::Pending, PaymentStatus::ActionRequired], fn () => $payment->markSucceeded($now), 'VerifiedPaymentSucceeded'),
            ProviderPaymentVerificationOutcome::Failed => $this->transition($payment, [PaymentStatus::Pending, PaymentStatus::ActionRequired], fn () => $payment->markFailed('provider_failed', $now), 'VerifiedPaymentFailed'),
            ProviderPaymentVerificationOutcome::Expired => $this->transition($payment, [PaymentStatus::Pending, PaymentStatus::ActionRequired], fn () => $payment->expire($now), 'PaymentExpired'),
            ProviderPaymentVerificationOutcome::ActionRequired => $this->transition($payment, [PaymentStatus::Pending], fn () => $payment->markActionRequired($now), 'PaymentRequiresAction'),
            ProviderPaymentVerificationOutcome::Pending => $this->transition($payment, [PaymentStatus::ActionRequired], fn () => $payment->resumePending($now), null),
        };
        if ($event === false) {
            $code = $payment->status === PaymentStatus::Succeeded && $outcome === ProviderPaymentVerificationOutcome::Succeeded ? PaymentVerificationApplicationResultCode::AlreadyReflected : PaymentVerificationApplicationResultCode::Regressive;
            $this->ignore($id, $token, $receipt, $payment, $code, $now);

            return;
        }
        $this->payments->save($payment);
        $this->audit->record('payment.verification.applied', $payment->id->value, $id, $now, $this->metadata($receipt, PaymentVerificationApplicationResultCode::Applied));
        if (is_string($event)) {
            $this->outbox->add($this->eventId($id, $event), $event, $payment->id->value, ['payment_id' => $payment->id->value, 'receipt_id' => $receipt->id], $now, eventVersion: 1);
        }
        $this->finish($id, $token, PaymentVerificationApplicationStatus::Applied, PaymentVerificationApplicationResultCode::Applied, $now);
    }

    /** @param list<PaymentStatus> $allowed */
    private function transition(Payment $payment, array $allowed, callable $mutation, ?string $event): string|bool
    {
        if (! in_array($payment->status, $allowed, true)) {
            return false;
        }
        $mutation();

        return $event ?? true;
    }

    private function reconcile(string $id, string $token, ProviderWebhookReceipt $receipt, Payment $payment, PaymentAttempt $attempt, string $reason, DateTimeImmutable $now): void
    {
        $this->reconciliations->open($receipt->id, $payment->id->value, $attempt->attemptReference, $reason, $now);
        $this->audit->record('payment.reconciliation.opened', $payment->id->value, $id, $now, $this->metadata($receipt, PaymentVerificationApplicationResultCode::ReconciliationRequired));
        $this->outbox->add($this->eventId($id, 'PaymentReconciliationRequired'), 'PaymentReconciliationRequired', $payment->id->value, ['payment_id' => $payment->id->value, 'receipt_id' => $receipt->id], $now);
        $this->finish($id, $token, PaymentVerificationApplicationStatus::ReconciliationRequired, PaymentVerificationApplicationResultCode::ReconciliationRequired, $now);
    }

    private function ignore(string $id, string $token, ProviderWebhookReceipt $receipt, Payment $payment, PaymentVerificationApplicationResultCode $code, DateTimeImmutable $now): void
    {
        $this->audit->record('payment.verification.ignored', $payment->id->value, $id, $now, $this->metadata($receipt, $code));
        $this->finish($id, $token, PaymentVerificationApplicationStatus::Ignored, $code, $now);
    }

    private function finish(string $id, string $token, PaymentVerificationApplicationStatus $status, PaymentVerificationApplicationResultCode $code, DateTimeImmutable $now): void
    {
        if (! $this->applications->complete($id, $token, $status, $code, $now)) {
            throw new TransientPaymentApplicationException('Application claim became stale.');
        }
    }

    private function completeEvidence(ProviderWebhookReceipt $receipt): bool
    {
        return $receipt->status === ProviderWebhookReceiptStatus::Processed && $receipt->resolvedPaymentId !== null
            && $receipt->resolvedPaymentAttemptReference !== null && $receipt->providerPaymentReference !== null && $receipt->verificationOutcome !== null
            && $receipt->verifiedAmountMinor !== null && $receipt->verifiedCurrency !== null && $receipt->providerObjectCorrelationPassed === true
            && (! $receipt->environmentCorrelationSupported || $receipt->environmentCorrelationPassed === true);
    }

    /** @return array{?PaymentAttempt,bool} */
    private function locateAttempt(Payment $payment, ProviderWebhookReceipt $receipt): array
    {
        foreach ($payment->attempts as $position => $attempt) {
            if ($attempt->attemptReference === $receipt->resolvedPaymentAttemptReference && $attempt->providerKey === $receipt->providerKey
                && $attempt->providerReference?->providerPaymentReference === $receipt->providerPaymentReference
                && $payment->amount->minorUnits === $receipt->verifiedAmountMinor && $payment->currency->value === $receipt->verifiedCurrency) {
                return [$attempt, $position === array_key_last($payment->attempts)];
            }
        }

        return [null, false];
    }

    /** @return array<string, string> */
    private function metadata(ProviderWebhookReceipt $receipt, PaymentVerificationApplicationResultCode $code): array
    {
        if ($receipt->resolvedPaymentAttemptReference === null) {
            throw new \LogicException('Complete verification evidence must identify its Payment attempt.');
        }

        return ['receipt_id' => $receipt->id, 'provider_key' => $receipt->providerKey, 'attempt_reference' => $receipt->resolvedPaymentAttemptReference, 'result_code' => $code->value];
    }

    private function eventId(string $applicationId, string $type): string
    {
        $hex = substr(hash('sha256', $applicationId.'|'.$type), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 3) | 8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
