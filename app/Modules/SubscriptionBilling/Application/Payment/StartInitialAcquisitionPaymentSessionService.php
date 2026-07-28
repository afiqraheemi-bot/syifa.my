<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\InitialAcquisitionCheckoutStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\StartInitialAcquisitionPaymentSessionCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CreatePaymentSessionInput;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionCreationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use LogicException;

final readonly class StartInitialAcquisitionPaymentSessionService
{
    public function __construct(
        private InitialAcquisitionCheckoutStoreInterface $checkouts,
        private PaymentSessionCreationInterface $sessions,
    ) {}

    public function execute(
        StartInitialAcquisitionPaymentSessionCommand $command,
    ): PaymentSession|PaymentSessionUnavailable {
        $state = $this->checkouts->begin(
            $command->clinicRegistrationReference,
            $command->commercialOfferReference,
            $command->paymentId,
            $command->commercialOfferValidUntil,
            $command->occurredAt,
        );

        if ($state->clinicRegistrationReference !== $command->clinicRegistrationReference
            || $state->commercialOfferReference !== $command->commercialOfferReference
            || $state->paymentId !== $command->paymentId) {
            throw new LogicException('Initial acquisition checkout lineage cannot be changed.');
        }

        if ($state->stage === 'session_ready' && $state->session !== null) {
            return $state->session;
        }

        try {
            $session = $this->sessions->create(new CreatePaymentSessionInput(
                renewalId: $command->clinicRegistrationReference,
                paymentId: $command->paymentId,
                commercialOfferValidUntil: $command->commercialOfferValidUntil,
                idempotencyKey: 'initial-acquisition-session:'.$command->paymentId,
                requestedAt: $command->occurredAt,
            ));
        } catch (PaymentProviderConfigurationException) {
            return new PaymentSessionUnavailable('provider_not_ready');
        }

        if ($session instanceof PaymentSessionUnavailable) {
            return $session;
        }

        $ready = $this->checkouts->sessionReady(
            $state->applicationId,
            $command->paymentId,
            $session,
            $command->occurredAt,
        );

        return $ready->session
            ?? throw new LogicException('Ready acquisition checkout must retain its Payment Session.');
    }
}
