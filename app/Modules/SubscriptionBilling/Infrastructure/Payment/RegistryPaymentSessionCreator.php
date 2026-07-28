<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentNotFoundException;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CreatePaymentSessionInput;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionCreationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RedirectAction;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\PaymentAttempt;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use DateTimeImmutable;
use LogicException;

final readonly class RegistryPaymentSessionCreator implements PaymentSessionCreationInterface
{
    public function __construct(
        private PaymentIdentifierGeneratorInterface $identifiers,
        private PaymentRepositoryInterface $payments,
        private PaymentProviderRegistryInterface $providers,
        private PaymentAuditInterface $audit,
        private PaymentTransactionInterface $transactions,
    ) {}

    public function create(CreatePaymentSessionInput $input): PaymentSession|PaymentSessionUnavailable
    {
        /** @var array{Payment,string,string} $prepared */
        $prepared = $this->transactions->run(function () use ($input): array {
            $payment = $this->payments->find(new PaymentId($input->paymentId));
            if ($payment === null) {
                throw new PaymentNotFoundException('Payment was not found.');
            }
            $attempt = $this->currentAttempt($payment);
            if ($attempt !== null && $attempt->status === PaymentStatus::Draft) {
                $provider = $this->providers->forExistingAttempt($attempt->providerKey);

                return [$payment, $attempt->attemptReference, $provider->providerKey()];
            }
            $provider = $this->providers->defaultForNewAttempt();
            $attemptReference = $this->identifiers->generate();
            $payment->start($attemptReference, $provider->providerKey(), $input->requestedAt);
            $this->payments->save($payment);
            $this->audit->record('payment.attempt.bound', $payment, $input->requestedAt, $input->renewalId);

            return [$payment, $attemptReference, $provider->providerKey()];
        });

        [$payment, $attemptReference, $providerKey] = $prepared;
        $provider = $this->providers->forExistingAttempt($providerKey);
        $result = $provider->start(new ProviderPaymentRequest(
            $payment->id->value,
            $payment->amount->minorUnits,
            $payment->currency->value,
            $payment->idempotencyKey->value,
            $input->renewalId,
            $attemptReference,
            $input->commercialOfferValidUntil,
        ));
        if ($result->providerKey !== $providerKey || $result->redirectDestination === null
            || $result->expiresAt === null || $result->expiryAuthority === ExpiryAuthority::None) {
            return new PaymentSessionUnavailable('provider_session_invalid');
        }

        $now = new DateTimeImmutable;
        $this->transactions->run(function () use ($input, $attemptReference, $providerKey, $result, $now): void {
            $payment = $this->payments->find(new PaymentId($input->paymentId));
            if ($payment === null) {
                throw new PaymentNotFoundException('Payment was not found.');
            }
            $attempt = $this->currentAttempt($payment);
            if ($attempt === null || $attempt->attemptReference !== $attemptReference || $attempt->providerKey !== $providerKey) {
                throw new LogicException('PaymentAttempt provider binding changed during session creation.');
            }
            $payment->markPending(
                new ProviderReference($providerKey, $result->providerPaymentReference),
                $now,
            );
            $this->payments->save($payment);
            $this->audit->record('payment.session.ready', $payment, $now, $input->renewalId);
        });

        return new PaymentSession(
            $this->identifiers->generate(),
            new RedirectAction($result->redirectDestination),
            $result->expiresAt,
            $result->expiryAuthority,
        );
    }

    private function currentAttempt(Payment $payment): ?PaymentAttempt
    {
        if ($payment->attempts === []) {
            return null;
        }

        return $payment->attempts[count($payment->attempts) - 1];
    }
}
