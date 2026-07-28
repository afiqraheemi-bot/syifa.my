<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionCheckoutInterface;
use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionCheckoutResult;
use App\Modules\ClinicRegistration\Contracts\Checkout\StartPublicInitialAcquisitionCheckoutCommand;
use App\Modules\ClinicRegistration\Contracts\Queries\ClinicRegistrationQueryInterface;
use App\Modules\Commercial\Application\Exceptions\ClinicRegistrationOwnershipMismatchException;
use App\Modules\Commercial\Application\PrepareCommercialOfferService;
use App\Modules\Commercial\Contracts\Commands\PrepareInitialCommercialOfferCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\CreateInitialAcquisitionPaymentCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\StartInitialAcquisitionPaymentSessionCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use DateTimeImmutable;

final readonly class StartPublicInitialAcquisitionCheckoutService implements PublicInitialAcquisitionCheckoutInterface
{
    public function __construct(
        private ClinicRegistrationQueryInterface $registrations,
        private PrepareCommercialOfferService $offers,
        private CreateInitialAcquisitionPaymentService $payments,
        private StartInitialAcquisitionPaymentSessionService $sessions,
    ) {}

    public function execute(
        StartPublicInitialAcquisitionCheckoutCommand $command,
    ): PublicInitialAcquisitionCheckoutResult {
        $registration = $this->registrations->currentForTrackingCredential($command->trackingCredential);
        if ($registration === null || $registration->status !== 'submitted'
            || $registration->selectedPlanOfferingReference !== $command->planOfferingId
            || $registration->reservedTenantId === null) {
            throw new ClinicRegistrationOwnershipMismatchException('Submitted Clinic Registration ownership could not be established.');
        }

        $offer = $this->offers->executeForInitialAcquisition(new PrepareInitialCommercialOfferCommand(
            $command->trackingCredential,
            $command->planOfferingId,
            $command->occurredAt,
            $command->correlationId,
        ));
        $payment = $this->payments->execute(new CreateInitialAcquisitionPaymentCommand(
            $registration->id,
            $offer->id,
            $registration->reservedTenantId,
            $command->occurredAt,
            $command->correlationId,
        ));
        $session = $this->sessions->execute(new StartInitialAcquisitionPaymentSessionCommand(
            $registration->id,
            $offer->id,
            $payment->paymentId,
            new DateTimeImmutable($offer->expiresAt),
            $command->occurredAt,
            $command->correlationId,
        ));

        if ($session instanceof PaymentSessionUnavailable) {
            return $session->reasonCode === 'provider_not_ready'
                ? PublicInitialAcquisitionCheckoutResult::providerNotReady()
                : PublicInitialAcquisitionCheckoutResult::unavailable();
        }

        return PublicInitialAcquisitionCheckoutResult::ready($session->redirectAction->destination);
    }
}
