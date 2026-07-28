<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Application\Payment\StartInitialAcquisitionPaymentSessionService;
use App\Modules\SubscriptionBilling\Contracts\Payment\InitialAcquisitionCheckoutState;
use App\Modules\SubscriptionBilling\Contracts\Payment\InitialAcquisitionCheckoutStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;
use App\Modules\SubscriptionBilling\Contracts\Payment\StartInitialAcquisitionPaymentSessionCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CreatePaymentSessionInput;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionCreationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\RegistryPaymentSessionCreator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InitialAcquisitionCheckoutSessionTest extends TestCase
{
    public function test_checkout_binds_one_attempt_and_reuses_one_hosted_session(): void
    {
        $payments = new AcquisitionSessionPaymentRepository($this->payment());
        $provider = new AcquisitionSessionProvider;
        $creator = new RegistryPaymentSessionCreator(
            new AcquisitionSessionIdentifierGenerator(['attempt-1', 'session-1']),
            $payments,
            $provider,
            new AcquisitionSessionAudit,
            new AcquisitionSessionTransaction,
        );
        $store = new AcquisitionSessionStore;
        $service = new StartInitialAcquisitionPaymentSessionService($store, $creator);
        $command = new StartInitialAcquisitionPaymentSessionCommand(
            'registration-1',
            'offer-1',
            'payment-1',
            new DateTimeImmutable('2026-07-21T00:30:00Z'),
            new DateTimeImmutable('2026-07-21T00:00:00Z'),
            'correlation-1',
        );

        $first = $service->execute($command);
        $second = $service->execute($command);

        self::assertInstanceOf(PaymentSession::class, $first);
        self::assertInstanceOf(PaymentSession::class, $second);
        self::assertSame($first->sessionId, $second->sessionId);
        self::assertSame(1, $provider->startCalls);
        self::assertCount(1, $payments->payment->attempts);
        self::assertSame('toyyibpay', $payments->payment->attempts[0]->providerKey);
        self::assertSame('pending', $payments->payment->attempts[0]->status->value);
    }

    public function test_provider_configuration_failure_returns_provider_not_ready(): void
    {
        $result = (new StartInitialAcquisitionPaymentSessionService(
            new AcquisitionSessionStore,
            new NotReadyAcquisitionSessionCreator,
        ))->execute(new StartInitialAcquisitionPaymentSessionCommand(
            'registration-1',
            'offer-1',
            'payment-1',
            new DateTimeImmutable('2026-07-21T00:30:00Z'),
            new DateTimeImmutable('2026-07-21T00:00:00Z'),
            'correlation-1',
        ));

        self::assertInstanceOf(PaymentSessionUnavailable::class, $result);
        self::assertSame('provider_not_ready', $result->reasonCode);
    }

    private function payment(): Payment
    {
        return Payment::createInitialAcquisition(
            new PaymentId('payment-1'),
            new PaymentReference('offer-1'),
            new PaymentReference('registration-1'),
            new TenantId('00000000-0000-4000-8000-000000000006'),
            new PaymentAmount(120000),
            new PaymentCurrency('MYR'),
            new IdempotencyKey('initial-acquisition-payment-1'),
            new DateTimeImmutable('2026-07-21T00:00:00Z'),
        );
    }
}

final class AcquisitionSessionStore implements InitialAcquisitionCheckoutStoreInterface
{
    private ?InitialAcquisitionCheckoutState $state = null;

    public function begin(
        string $clinicRegistrationReference,
        string $commercialOfferReference,
        string $paymentId,
        DateTimeImmutable $commercialOfferValidUntil,
        DateTimeImmutable $occurredAt,
    ): InitialAcquisitionCheckoutState {
        return $this->state ??= new InitialAcquisitionCheckoutState(
            'checkout-1',
            $clinicRegistrationReference,
            $commercialOfferReference,
            $paymentId,
            'session_pending',
        );
    }

    public function sessionReady(
        string $applicationId,
        string $paymentId,
        PaymentSession $session,
        DateTimeImmutable $occurredAt,
    ): InitialAcquisitionCheckoutState {
        return $this->state = new InitialAcquisitionCheckoutState(
            $applicationId,
            'registration-1',
            'offer-1',
            $paymentId,
            'session_ready',
            $session,
        );
    }
}

final class AcquisitionSessionPaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(public Payment $payment) {}

    public function find(PaymentId $paymentId): ?Payment
    {
        return $paymentId->value === $this->payment->id->value ? $this->payment : null;
    }

    public function findByIdempotencyKey(IdempotencyKey $idempotencyKey): ?Payment
    {
        return $idempotencyKey->value === $this->payment->idempotencyKey->value ? $this->payment : null;
    }

    public function findByProviderReference(ProviderReference $providerReference): ?Payment
    {
        return null;
    }

    public function save(Payment $payment): void
    {
        $payment->synchronizeVersion($payment->version() + 1);
        $this->payment = $payment;
    }
}

final class AcquisitionSessionProvider implements PaymentProviderInterface, PaymentProviderRegistryInterface
{
    public int $startCalls = 0;

    public function providerKey(): string
    {
        return 'toyyibpay';
    }

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult
    {
        $this->startCalls++;

        return new ProviderPaymentResult(
            'toyyibpay',
            'bill-code-1',
            'https://toyyibpay.com/bill-code-1',
            $request->commercialOfferValidUntil,
            ExpiryAuthority::CommercialOffer,
        );
    }

    public function defaultForNewAttempt(): PaymentProviderInterface
    {
        return $this;
    }

    public function forNewAttempt(string $providerKey): PaymentProviderInterface
    {
        return $this;
    }

    public function forExistingAttempt(string $providerKey): PaymentProviderInterface
    {
        return $this;
    }

    public function credentialsConfigured(): bool
    {
        return true;
    }

    public function verifyConfiguration(): ProviderConfigurationVerification
    {
        return new ProviderConfigurationVerification(true, 'verified');
    }

    public function verify(ProviderPaymentVerificationRequest $request): ProviderPaymentVerification
    {
        throw new \LogicException;
    }

    public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent
    {
        throw new \LogicException;
    }
}

final class AcquisitionSessionIdentifierGenerator implements PaymentIdentifierGeneratorInterface
{
    /** @param list<string> $ids */
    public function __construct(private array $ids) {}

    public function generate(): string
    {
        return array_shift($this->ids) ?? 'identifier-fallback';
    }
}

final class AcquisitionSessionAudit implements PaymentAuditInterface
{
    public function record(
        string $action,
        Payment $payment,
        DateTimeImmutable $occurredAt,
        string $correlationId,
    ): void {}
}

final class AcquisitionSessionTransaction implements PaymentTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}

final class NotReadyAcquisitionSessionCreator implements PaymentSessionCreationInterface
{
    public function create(CreatePaymentSessionInput $input): PaymentSession|PaymentSessionUnavailable
    {
        throw new PaymentProviderConfigurationException('No ready provider.');
    }
}
