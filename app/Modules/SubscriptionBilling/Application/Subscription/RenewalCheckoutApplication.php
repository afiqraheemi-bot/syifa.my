<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Renewal\BeginRenewalCheckoutCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CreatePaymentSessionInput;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionCreationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutStoreInterface;
use LogicException;

final readonly class RenewalCheckoutApplication
{
    public function __construct(
        private RenewalCheckoutStoreInterface $checkouts,
        private PaymentSessionCreationInterface $sessions,
    ) {}

    public function execute(BeginRenewalCheckoutCommand $command): PaymentSession|PaymentSessionUnavailable
    {
        $state = $this->checkouts->begin($command);
        if ($state->paymentId !== $command->paymentId || $state->renewalId !== $command->renewalId) {
            throw new LogicException('Renewal checkout lineage cannot be changed.');
        }
        if ($state->stage === 'session_ready' && $state->session !== null) {
            return $state->session;
        }
        if ($state->stage === 'failed') {
            return new PaymentSessionUnavailable($state->safeFailureCode ?? 'checkout_failed');
        }

        $result = $this->sessions->create(new CreatePaymentSessionInput(
            $command->renewalId,
            $command->paymentId,
            $command->commercialOfferValidUntil,
            $command->idempotencyKey,
            $command->occurredAt,
        ));
        if ($result instanceof PaymentSessionUnavailable) {
            $this->checkouts->fail($state->applicationId, $result->reasonCode, $command->correlationId);

            return $result;
        }

        $completed = $this->checkouts->sessionReady(
            $state->applicationId,
            $command->paymentId,
            $result,
            $command->correlationId,
        );

        return $completed->session
            ?? throw new LogicException('Ready renewal checkout must retain its Payment Session.');
    }
}
