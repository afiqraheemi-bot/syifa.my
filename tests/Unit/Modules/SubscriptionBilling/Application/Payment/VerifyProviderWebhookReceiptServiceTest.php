<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\ProviderVerificationRetryPolicy;
use App\Modules\SubscriptionBilling\Application\Payment\VerifyProviderWebhookReceiptService;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\MalformedProviderVerificationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\RetryableProviderVerificationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\NewProviderWebhookReceiptData;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAttemptResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationOutcome;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationClockInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceipt;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptClaim;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptCompletion;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRegistrationResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptStatus;
use App\Modules\SubscriptionBilling\Contracts\Payment\ResolvedPaymentAttempt;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\PaymentAttempt;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class VerifyProviderWebhookReceiptServiceTest extends TestCase
{
    public function test_historical_success_is_processed_without_saving_payment(): void
    {
        [$service, $receipts, $payments, $provider] = $this->scenario();
        $service->execute('receipt-1');

        self::assertSame(ProviderWebhookReceiptStatus::Processed, $receipts->completion?->status);
        self::assertSame(ProviderPaymentVerificationOutcome::Succeeded, $receipts->completion?->verification?->outcome);
        self::assertFalse($receipts->completion?->attempt?->isCurrent);
        self::assertSame(0, $payments->saveCount);
        self::assertSame('provider-a', $provider->lastRequest?->providerKey);
    }

    public function test_amount_mismatch_is_quarantined(): void
    {
        [$service, $receipts, , $provider] = $this->scenario();
        $provider->amount = 999;
        $service->execute('receipt-1');

        self::assertSame(ProviderWebhookReceiptStatus::Quarantined, $receipts->completion?->status);
        self::assertSame('verified_amount_mismatch', $receipts->completion?->safeFailureLabel);
    }

    public function test_duplicate_delivery_is_harmless_when_claim_is_unavailable(): void
    {
        [$service, $receipts, $payments, $provider] = $this->scenario();
        $receipts->claimable = false;
        $service->execute('receipt-1');

        self::assertNull($provider->lastRequest);
        self::assertNull($receipts->completion);
        self::assertSame(0, $payments->saveCount);
    }

    public function test_retry_after_is_honored_and_capped(): void
    {
        foreach ([[120, 120, '2026-07-24T00:02:00+00:00'], [86400, 21600, '2026-07-24T06:00:00+00:00']] as [$retryAfter, $expectedDelay, $expectedAt]) {
            [$service, $receipts, $payments, $provider, $jobs] = $this->scenario();
            $provider->failure = new RetryableProviderVerificationException('provider detail', $retryAfter);
            $service->execute('receipt-1');

            self::assertSame(ProviderWebhookReceiptStatus::RetryPending, $receipts->completion?->status);
            self::assertSame($expectedAt, $receipts->completion?->nextVerificationAttemptAt?->format(DATE_ATOM));
            self::assertSame($expectedDelay, $jobs->delaySeconds);
            self::assertSame(0, $payments->saveCount);
            self::assertSame('provider_transport_retry', $receipts->completion?->safeFailureLabel);
        }
    }

    public function test_eighth_transport_attempt_is_exhausted_without_ninth_job(): void
    {
        foreach (range(1, 7) as $attemptNumber) {
            [$retryService, $retryReceipts, , $retryProvider, $retryJobs] = $this->scenario($attemptNumber);
            $retryProvider->failure = new RetryableProviderVerificationException('provider unavailable');
            $retryService->execute('receipt-1');
            self::assertSame(ProviderWebhookReceiptStatus::RetryPending, $retryReceipts->completion?->status);
            self::assertSame(1, $retryJobs->dispatchCount);
        }

        [$service, $receipts, $payments, $provider, $jobs] = $this->scenario(8);
        $provider->failure = new RetryableProviderVerificationException('provider unavailable');
        $service->execute('receipt-1');

        self::assertSame(ProviderWebhookReceiptStatus::Exhausted, $receipts->completion?->status);
        self::assertNull($receipts->completion?->nextVerificationAttemptAt);
        self::assertSame('provider_transport_exhausted', $receipts->completion?->safeFailureLabel);
        self::assertSame(0, $jobs->dispatchCount);
        self::assertSame(0, $payments->saveCount);
    }

    public function test_malformed_first_attempt_retries_and_second_quarantines(): void
    {
        [$firstService, $firstReceipts, $payments, $firstProvider, $jobs] = $this->scenario(1);
        $firstProvider->failure = new MalformedProviderVerificationException('raw response detail');
        $firstService->execute('receipt-1');
        self::assertSame(ProviderWebhookReceiptStatus::RetryPending, $firstReceipts->completion?->status);
        self::assertNotNull($firstReceipts->completion?->nextVerificationAttemptAt);
        self::assertSame(1, $jobs->dispatchCount);

        [$secondService, $secondReceipts, , $secondProvider, $secondJobs] = $this->scenario(2);
        $secondProvider->failure = new MalformedProviderVerificationException('raw response detail');
        $secondService->execute('receipt-1');
        self::assertSame(ProviderWebhookReceiptStatus::Quarantined, $secondReceipts->completion?->status);
        self::assertNull($secondReceipts->completion?->nextVerificationAttemptAt);
        self::assertSame('provider_response_malformed', $secondReceipts->completion?->safeFailureLabel);
        self::assertSame(0, $secondJobs->dispatchCount);
        self::assertSame(0, $payments->saveCount);
    }

    public function test_exponential_backoff_and_jitter_seams_are_bounded(): void
    {
        $minimum = new ProviderVerificationRetryPolicy(jitter: static fn (int $maximum): int => 0);
        $maximum = new ProviderVerificationRetryPolicy(jitter: static fn (int $maximum): int => $maximum);
        self::assertSame(120, $minimum->delaySeconds(3));
        self::assertSame(144, $maximum->delaySeconds(3));
        self::assertSame(1800, $maximum->delaySeconds(8));
    }

    /** @return array{VerifyProviderWebhookReceiptService, MemoryVerificationReceipts, MemoryVerificationPayments, VerificationProvider, RecordingVerificationJobs} */
    private function scenario(int $attemptCount = 1): array
    {
        $attempt = new ResolvedPaymentAttempt('11111111-1111-4111-8111-111111111111', 'attempt-old', 'provider-a', 'provider-ref', 2550, 'MYR', 0, false);
        $receipts = new MemoryVerificationReceipts;
        $receipts->attemptCount = $attemptCount;
        $payments = new MemoryVerificationPayments($this->payment());
        $provider = new VerificationProvider;
        $jobs = new RecordingVerificationJobs;
        $service = new VerifyProviderWebhookReceiptService(
            $receipts,
            new class($attempt) implements PaymentAttemptResolverInterface
            {
                public function __construct(private ResolvedPaymentAttempt $attempt) {}

                public function resolve(string $providerKey, string $providerPaymentReference): ?ResolvedPaymentAttempt
                {
                    return $this->attempt;
                }
            },
            $payments,
            new class($provider) implements PaymentProviderRegistryInterface
            {
                public function __construct(private PaymentProviderInterface $provider) {}

                public function defaultForNewAttempt(): PaymentProviderInterface
                {
                    throw new \LogicException;
                }

                public function forNewAttempt(string $providerKey): PaymentProviderInterface
                {
                    throw new \LogicException;
                }

                public function forExistingAttempt(string $providerKey): PaymentProviderInterface
                {
                    return $this->provider;
                }
            },
            $jobs,
            new ProviderVerificationRetryPolicy(jitter: static fn (int $maximum): int => 0),
            new FixedVerificationClock(new DateTimeImmutable('2026-07-24T00:00:00Z')),
        );

        return [$service, $receipts, $payments, $provider, $jobs];
    }

    private function payment(): Payment
    {
        $time = new DateTimeImmutable('2026-07-24T00:00:00Z');

        return new Payment(
            new PaymentId('11111111-1111-4111-8111-111111111111'), new PaymentReference('offer'),
            new PaymentReference('registration'), new PaymentReference('identity'), new PaymentAmount(2550),
            new PaymentCurrency('MYR'), new IdempotencyKey('idem'), PaymentStatus::Pending,
            new ProviderReference('provider-b', 'new-ref'), null, $time, $time,
            [
                new PaymentAttempt('attempt-old', 'provider-a', PaymentStatus::Succeeded, new ProviderReference('provider-a', 'provider-ref'), null, $time, $time),
                new PaymentAttempt('attempt-new', 'provider-b', PaymentStatus::Pending, new ProviderReference('provider-b', 'new-ref'), null, $time, $time),
            ], 1,
        );
    }
}

final class MemoryVerificationReceipts implements ProviderWebhookReceiptRepositoryInterface
{
    public bool $claimable = true;

    public int $attemptCount = 1;

    public ?ProviderWebhookReceiptCompletion $completion = null;

    public function register(NewProviderWebhookReceiptData $data): ProviderWebhookReceiptRegistrationResult
    {
        throw new \LogicException;
    }

    public function find(string $providerKey, string $providerEventId): ?ProviderWebhookReceipt
    {
        return null;
    }

    public function findById(string $receiptId): ?ProviderWebhookReceipt
    {
        return null;
    }

    public function claim(string $receiptId, DateTimeImmutable $now, int $leaseSeconds): ?ProviderWebhookReceiptClaim
    {
        if (! $this->claimable) {
            return null;
        }

        return new ProviderWebhookReceiptClaim(new ProviderWebhookReceipt('receipt-1', 'provider-a', 'event-1', 'payment.succeeded', ProviderWebhookReceiptStatus::Processing, $now, 'provider-ref', processingStartedAt: $now, processingClaimToken: '22222222-2222-4222-8222-222222222222', verificationAttemptCount: $this->attemptCount), '22222222-2222-4222-8222-222222222222');
    }

    public function complete(string $receiptId, string $claimToken, ProviderWebhookReceiptCompletion $completion): bool
    {
        $this->completion = $completion;

        return true;
    }
}

final class MemoryVerificationPayments implements PaymentRepositoryInterface
{
    public int $saveCount = 0;

    public function __construct(private Payment $payment) {}

    public function find(PaymentId $paymentId): ?Payment
    {
        return $this->payment;
    }

    public function findByIdempotencyKey(IdempotencyKey $idempotencyKey): ?Payment
    {
        return null;
    }

    public function findByProviderReference(ProviderReference $providerReference): ?Payment
    {
        return null;
    }

    public function save(Payment $payment): void
    {
        $this->saveCount++;
    }
}

final class VerificationProvider implements PaymentProviderInterface
{
    public int $amount = 2550;

    public ?\Throwable $failure = null;

    public ?ProviderPaymentVerificationRequest $lastRequest = null;

    public function providerKey(): string
    {
        return 'provider-a';
    }

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult
    {
        throw new \LogicException;
    }

    public function verify(ProviderPaymentVerificationRequest $request): ProviderPaymentVerification
    {
        $this->lastRequest = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new ProviderPaymentVerification('provider-a', 'provider-ref', ProviderPaymentVerificationOutcome::Succeeded, $this->amount, 'MYR', new DateTimeImmutable, true, false, false);
    }

    public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent
    {
        throw new \LogicException;
    }

    public function credentialsConfigured(): bool
    {
        return true;
    }

    public function verifyConfiguration(): ProviderConfigurationVerification
    {
        throw new \LogicException;
    }
}

final class RecordingVerificationJobs implements ProviderVerificationJobDispatcherInterface
{
    public int $dispatchCount = 0;

    public ?int $delaySeconds = null;

    public function dispatch(string $receiptId, int $delaySeconds = 0): void
    {
        $this->dispatchCount++;
        $this->delaySeconds = $delaySeconds;
    }
}

final readonly class FixedVerificationClock implements ProviderVerificationClockInterface
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
