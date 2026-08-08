<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\AcquisitionOffer\Contracts\Checkout\CommercialOfferCheckoutInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\CommercialOfferMissingTenantIdException;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\CommercialOfferUnavailableForPaymentException;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\UnauthorizedPaymentInitiationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\CreatePaymentCommand;
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

final readonly class CreatePaymentService
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

    public function execute(PlatformPrincipal $principal, CreatePaymentCommand $command): PaymentData
    {
        return $this->transactions->run(function () use ($principal, $command): PaymentData {
            $idempotencyKey = new IdempotencyKey($command->idempotencyKey);
            $existing = $this->payments->findByIdempotencyKey($idempotencyKey);

            if ($existing !== null) {
                return $this->data->fromDomain($existing);
            }

            $offer = $this->checkout->offerForCheckout($command->commercialOfferId, 'payment', $command->occurredAt);

            if ($offer === null || $offer->status !== 'prepared') {
                throw new CommercialOfferUnavailableForPaymentException('Commercial Offer is not available for Payment.');
            }

            if ($offer->platformIdentityId !== $principal->platformIdentityId) {
                throw new UnauthorizedPaymentInitiationException('Platform Principal does not own this Commercial Offer.');
            }

            if ($offer->tenantId === null) {
                throw new CommercialOfferMissingTenantIdException('Commercial Offer has not reserved a tenant id.');
            }

            $payment = Payment::create(
                id: new PaymentId($this->identifiers->generate()),
                commercialOfferId: new PaymentReference($offer->id),
                clinicRegistrationId: new PaymentReference($offer->clinicRegistrationId),
                platformIdentityId: new PaymentReference($principal->platformIdentityId),
                tenantId: new TenantId($offer->tenantId),
                amount: new PaymentAmount($offer->totalAmountMinor),
                currency: new PaymentCurrency($offer->currency),
                idempotencyKey: $idempotencyKey,
                occurredAt: $command->occurredAt,
            );

            $this->claims->execute($offer, $payment->id->value, $command->occurredAt, $command->correlationId);
            $this->payments->save($payment);
            $this->audit->record('payment.create', $payment, $command->occurredAt, $command->correlationId);

            return $this->data->fromDomain($payment);
        });
    }
}
