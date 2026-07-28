<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\RenewalCheckoutApplication;
use App\Modules\SubscriptionBilling\Application\Subscription\RenewalOutcomeApplication;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ApplyRenewalOutcomeCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\BeginRenewalCheckoutCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CreatePaymentSessionInput;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionCreationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RedirectAction;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutState;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOutcomeResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOutcomeStoreInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RenewalCheckoutFoundationTest extends TestCase
{
    public function test_payment_session_requires_https_redirect_and_authoritative_expiry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PaymentSession('session-1', new RedirectAction('http://checkout.example.test'), new DateTimeImmutable('+1 hour'), ExpiryAuthority::Provider);
    }

    public function test_checkout_persists_lineage_then_normalizes_ready_session(): void
    {
        $session = new PaymentSession(
            'session-1',
            new RedirectAction('https://checkout.example.test/session/1'),
            new DateTimeImmutable('2027-01-01T01:00:00Z'),
            ExpiryAuthority::CommercialOffer,
        );
        $repository = new RecordedCheckoutRepository;
        $application = new RenewalCheckoutApplication($repository, new FixedSessionCreator($session));
        $result = $application->execute($this->checkoutCommand());

        self::assertSame($session, $result);
        self::assertSame(['begin', 'ready'], $repository->calls);
        self::assertSame('payment-1', $repository->paymentId);
    }

    public function test_unavailable_session_fails_closed_without_redirect(): void
    {
        $repository = new RecordedCheckoutRepository;
        $application = new RenewalCheckoutApplication(
            $repository,
            new FixedSessionCreator(new PaymentSessionUnavailable('expiry_missing')),
        );

        $result = $application->execute($this->checkoutCommand());

        self::assertInstanceOf(PaymentSessionUnavailable::class, $result);
        self::assertSame('expiry_missing', $result->reasonCode);
        self::assertSame(['begin', 'failed'], $repository->calls);
    }

    public function test_outcome_application_accepts_only_authoritative_terminal_outcomes(): void
    {
        $repository = new RecordedOutcomeRepository;
        $application = new RenewalOutcomeApplication($repository);
        $result = $application->execute(new ApplyRenewalOutcomeCommand(
            'event-1', 'payment-1', 'succeeded', 'correlation-1', new DateTimeImmutable,
        ));
        self::assertSame('applied', $result->code);

        $this->expectException(InvalidArgumentException::class);
        $application->execute(new ApplyRenewalOutcomeCommand(
            'event-2', 'payment-1', 'pending', 'correlation-1', new DateTimeImmutable,
        ));
    }

    private function checkoutCommand(): BeginRenewalCheckoutCommand
    {
        return new BeginRenewalCheckoutCommand(
            'renewal-1',
            'payment-1',
            new DateTimeImmutable('2027-01-01T01:00:00Z'),
            'checkout-key',
            'correlation-1',
            new DateTimeImmutable('2027-01-01T00:00:00Z'),
        );
    }
}

final class RecordedCheckoutRepository implements RenewalCheckoutStoreInterface
{
    /** @var list<string> */
    public array $calls = [];

    public ?string $paymentId = null;

    public function begin(BeginRenewalCheckoutCommand $command): RenewalCheckoutState
    {
        $this->calls[] = 'begin';
        $this->paymentId = $command->paymentId;

        return new RenewalCheckoutState('application-1', $command->renewalId, $command->paymentId, 'session_pending');
    }

    public function sessionReady(string $applicationId, string $paymentId, PaymentSession $session, string $correlationId): RenewalCheckoutState
    {
        $this->calls[] = 'ready';

        return new RenewalCheckoutState($applicationId, 'renewal-1', $paymentId, 'session_ready', $session);
    }

    public function fail(string $applicationId, string $safeFailureCode, string $correlationId): RenewalCheckoutState
    {
        $this->calls[] = 'failed';

        return new RenewalCheckoutState($applicationId, 'renewal-1', 'payment-1', 'failed', safeFailureCode: $safeFailureCode);
    }
}

final readonly class FixedSessionCreator implements PaymentSessionCreationInterface
{
    public function __construct(private PaymentSession|PaymentSessionUnavailable $result) {}

    public function create(CreatePaymentSessionInput $input): PaymentSession|PaymentSessionUnavailable
    {
        return $this->result;
    }
}

final class RecordedOutcomeRepository implements RenewalOutcomeStoreInterface
{
    public function apply(ApplyRenewalOutcomeCommand $command): RenewalOutcomeResult
    {
        return new RenewalOutcomeResult('applied', 'renewal-1');
    }
}
