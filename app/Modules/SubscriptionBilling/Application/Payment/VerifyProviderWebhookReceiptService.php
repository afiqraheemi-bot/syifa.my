<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderUnavailableException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\MalformedProviderVerificationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\RetryableProviderVerificationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAttemptResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationClockInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptCompletion;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptStatus;
use App\Modules\SubscriptionBilling\Contracts\Payment\ResolvedPaymentAttempt;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use DateTimeImmutable;

final readonly class VerifyProviderWebhookReceiptService
{
    public function __construct(
        private ProviderWebhookReceiptRepositoryInterface $receipts,
        private PaymentAttemptResolverInterface $attempts,
        private PaymentRepositoryInterface $payments,
        private PaymentProviderRegistryInterface $providers,
        private ProviderVerificationJobDispatcherInterface $jobs,
        private ProviderVerificationRetryPolicy $retryPolicy,
        private ProviderVerificationClockInterface $clock,
    ) {}

    public function execute(string $receiptId): void
    {
        $now = $this->clock->now();
        $claim = $this->receipts->claim($receiptId, $now, $this->retryPolicy->leaseSeconds);
        if ($claim === null) {
            return;
        }
        $receipt = $claim->receipt;
        if ($receipt->providerPaymentReference === null) {
            $this->terminal($receiptId, $claim->claimToken, ProviderWebhookReceiptStatus::Quarantined, 'provider_reference_missing', $now);

            return;
        }
        $attempt = $this->attempts->resolve($receipt->providerKey, $receipt->providerPaymentReference);
        if ($attempt === null) {
            $this->terminal($receiptId, $claim->claimToken, ProviderWebhookReceiptStatus::Quarantined, 'payment_attempt_unknown', $now);

            return;
        }
        $payment = $this->payments->find(new PaymentId($attempt->paymentId));
        $owned = false;
        if ($payment !== null) {
            foreach ($payment->attempts as $candidate) {
                if ($candidate->attemptReference === $attempt->attemptReference
                    && $candidate->providerKey === $attempt->providerKey
                    && $candidate->providerReference?->providerPaymentReference === $attempt->providerPaymentReference) {
                    $owned = true;
                    break;
                }
            }
        }
        if (! $owned) {
            $this->terminal($receiptId, $claim->claimToken, ProviderWebhookReceiptStatus::Quarantined, 'attempt_ownership_mismatch', $now, $attempt);

            return;
        }

        try {
            $verification = $this->providers->forExistingAttempt($attempt->providerKey)->verify(new ProviderPaymentVerificationRequest(
                $attempt->providerKey, $attempt->providerPaymentReference, $attempt->paymentId,
                $attempt->attemptReference, $attempt->expectedAmountMinor, $attempt->expectedCurrency,
            ));
            $failure = $this->correlationFailure($attempt->providerKey, $attempt->providerPaymentReference, $attempt->expectedAmountMinor, $attempt->expectedCurrency, $verification);
            if ($failure !== null) {
                $this->terminal($receiptId, $claim->claimToken, ProviderWebhookReceiptStatus::Quarantined, $failure, $this->clock->now(), $attempt, $verification);

                return;
            }
            $this->receipts->complete($receiptId, $claim->claimToken, new ProviderWebhookReceiptCompletion(
                ProviderWebhookReceiptStatus::Processed, $this->clock->now(), $attempt, $verification,
            ));
        } catch (RetryableProviderVerificationException $exception) {
            $this->retryTransport($receiptId, $claim->claimToken, $receipt->verificationAttemptCount, $exception->retryAfterSeconds, $attempt);
        } catch (MalformedProviderVerificationException) {
            $this->retryMalformed($receiptId, $claim->claimToken, $receipt->verificationAttemptCount, $attempt);
        } catch (PaymentProviderUnavailableException) {
            $this->retryTransport($receiptId, $claim->claimToken, $receipt->verificationAttemptCount, null, $attempt);
        } catch (PaymentProviderConfigurationException) {
            $this->terminal($receiptId, $claim->claimToken, ProviderWebhookReceiptStatus::Quarantined, 'attempt_provider_unavailable', $this->clock->now(), $attempt);
        }
    }

    private function correlationFailure(string $providerKey, string $reference, int $amount, string $currency, ProviderPaymentVerification $result): ?string
    {
        return match (true) {
            $result->providerKey !== $providerKey => 'provider_mismatch',
            $result->providerPaymentReference !== $reference => 'provider_reference_mismatch',
            $result->verifiedAmountMinor !== $amount => 'verified_amount_mismatch',
            strtoupper($result->verifiedCurrency) !== strtoupper($currency) => 'verified_currency_mismatch',
            ! $result->providerObjectCorrelationPassed => 'provider_object_correlation_failed',
            $result->environmentCorrelationSupported && ! $result->environmentCorrelationPassed => 'environment_correlation_failed',
            default => null,
        };
    }

    private function retryTransport(string $id, string $token, int $attemptCount, ?int $retryAfter, ResolvedPaymentAttempt $attempt): void
    {
        $now = $this->clock->now();
        if ($attemptCount >= $this->retryPolicy->transportMaxAttempts) {
            $this->terminal($id, $token, ProviderWebhookReceiptStatus::Exhausted, 'provider_transport_exhausted', $now, $attempt);

            return;
        }
        $delay = $this->retryPolicy->delaySeconds($attemptCount, $retryAfter);
        $next = $now->modify(sprintf('+%d seconds', $delay));
        if ($this->receipts->complete($id, $token, new ProviderWebhookReceiptCompletion(ProviderWebhookReceiptStatus::RetryPending, $now, $attempt, safeFailureLabel: 'provider_transport_retry', nextVerificationAttemptAt: $next))) {
            $this->jobs->dispatch($id, $delay);
        }
    }

    private function retryMalformed(string $id, string $token, int $attemptCount, ResolvedPaymentAttempt $attempt): void
    {
        $now = $this->clock->now();
        if ($attemptCount >= $this->retryPolicy->malformedMaxAttempts) {
            $this->terminal($id, $token, ProviderWebhookReceiptStatus::Quarantined, 'provider_response_malformed', $now, $attempt);

            return;
        }
        $delay = $this->retryPolicy->delaySeconds($attemptCount);
        $next = $now->modify(sprintf('+%d seconds', $delay));
        if ($this->receipts->complete($id, $token, new ProviderWebhookReceiptCompletion(ProviderWebhookReceiptStatus::RetryPending, $now, $attempt, safeFailureLabel: 'provider_response_retry', nextVerificationAttemptAt: $next))) {
            $this->jobs->dispatch($id, $delay);
        }
    }

    private function terminal(string $id, string $token, ProviderWebhookReceiptStatus $status, string $label, DateTimeImmutable $now, ?ResolvedPaymentAttempt $attempt = null, ?ProviderPaymentVerification $verification = null): void
    {
        $this->receipts->complete($id, $token, new ProviderWebhookReceiptCompletion($status, $now, $attempt, $verification, $label));
    }
}
