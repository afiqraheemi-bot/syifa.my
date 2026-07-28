<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\Commercial\Contracts\Checkout\CommercialOfferCheckoutInterface;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\CommercialOfferUnavailableForPaymentException;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\UnauthorizedPaymentInitiationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\CreateInitialAcquisitionPaymentCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentData;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\TenantId;

final readonly class CreateInitialAcquisitionPaymentService
{
    public function __construct(
        private PaymentIdentifierGeneratorInterface $identifiers,
        private CommercialOfferCheckoutInterface $checkout,
        private ClaimCommercialOfferService $claims,
        private PaymentRepositoryInterface $payments,
        private PaymentDataAssembler $data,
        private PaymentAuditInterface $audit,
        private PaymentTransactionInterface $transactions,
    ) {}

    public function execute(CreateInitialAcquisitionPaymentCommand $command): PaymentData
    {
        return $this->transactions->run(function () use ($command): PaymentData {
            $offer = $this->checkout->initialAcquisitionOfferForCheckout(
                $command->commercialOfferReference,
                $command->clinicRegistrationReference,
                'payment',
                $command->occurredAt,
            );

            if ($offer === null || ! in_array($offer->status, ['prepared', 'claimed'], true)) {
                throw new CommercialOfferUnavailableForPaymentException('Initial acquisition Commercial Offer is unavailable.');
            }

            if ($offer->platformIdentityId !== null
                || $offer->clinicRegistrationId !== $command->clinicRegistrationReference
                || $offer->tenantId !== $command->futureTenantId) {
                throw new UnauthorizedPaymentInitiationException('Clinic Registration does not own this initial acquisition Commercial Offer.');
            }

            $idempotencyKey = new IdempotencyKey($this->idempotencyKey($command));
            $existing = $this->payments->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                if ($existing->commercialOfferId->value !== $offer->id
                    || $existing->clinicRegistrationId->value !== $command->clinicRegistrationReference
                    || $existing->platformIdentityId !== null
                    || $existing->tenantId?->value !== $command->futureTenantId) {
                    throw new UnauthorizedPaymentInitiationException('Existing acquisition Payment lineage does not match.');
                }

                return $this->data->fromDomain($existing);
            }

            if ($offer->status !== 'prepared') {
                throw new CommercialOfferUnavailableForPaymentException('Initial acquisition Commercial Offer is already claimed.');
            }

            $payment = Payment::createInitialAcquisition(
                id: new PaymentId($this->identifiers->generate()),
                commercialOfferId: new PaymentReference($offer->id),
                clinicRegistrationId: new PaymentReference($command->clinicRegistrationReference),
                futureTenantId: new TenantId($command->futureTenantId),
                amount: new PaymentAmount($offer->totalAmountMinor),
                currency: new PaymentCurrency($offer->currency),
                idempotencyKey: $idempotencyKey,
                occurredAt: $command->occurredAt,
            );

            $this->claims->execute($offer, $payment->id->value, $command->occurredAt, $command->correlationId);
            $this->payments->save($payment);
            $this->audit->record('payment.create_initial_acquisition', $payment, $command->occurredAt, $command->correlationId);

            return $this->data->fromDomain($payment);
        });
    }

    private function idempotencyKey(CreateInitialAcquisitionPaymentCommand $command): string
    {
        return 'initial-acquisition:'.hash(
            'sha256',
            $command->clinicRegistrationReference.'|'.$command->commercialOfferReference,
        );
    }
}
