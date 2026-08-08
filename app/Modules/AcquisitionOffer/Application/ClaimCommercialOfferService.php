<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Application;

use App\Modules\AcquisitionOffer\Application\Audit\CommercialOfferAuditTrail;
use App\Modules\AcquisitionOffer\Application\Exceptions\CommercialOfferNotFoundException;
use App\Modules\AcquisitionOffer\Application\Exceptions\CommercialOfferVersionMismatchException;
use App\Modules\AcquisitionOffer\Application\Exceptions\UntrustedCommercialOfferConsumerException;
use App\Modules\AcquisitionOffer\Contracts\Checkout\CommercialOfferCheckoutInterface;
use App\Modules\AcquisitionOffer\Contracts\Commands\ClaimCommercialOfferCommand;
use App\Modules\AcquisitionOffer\Contracts\Data\CommercialOfferData;
use App\Modules\AcquisitionOffer\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\AcquisitionOffer\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\AcquisitionOffer\Contracts\Transactions\CommercialTransactionInterface;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferId;
use DateTimeImmutable;

final readonly class ClaimCommercialOfferService implements CommercialOfferCheckoutInterface
{
    public function __construct(
        private CommercialOfferRepositoryInterface $offers,
        private CommercialOfferDataAssembler $data,
        private CommercialOfferAuditTrail $audit,
        private CommercialOfferEventPublisherInterface $events,
        private TrustedCommercialOfferConsumers $trustedConsumers,
        private CommercialTransactionInterface $transactions,
    ) {}

    public function offerForCheckout(string $commercialOfferId, string $trustedConsumer, DateTimeImmutable $occurredAt): ?CommercialOfferData
    {
        if (! $this->trustedConsumers->trusts($trustedConsumer)) {
            throw new UntrustedCommercialOfferConsumerException('Commercial Offer consumer is not trusted.');
        }

        $offer = $this->offers->find(new CommercialOfferId($commercialOfferId));

        if ($offer === null || $offer->isExpiredAt($occurredAt)) {
            return null;
        }

        return $this->data->fromDomain($offer);
    }

    public function initialAcquisitionOfferForCheckout(
        string $commercialOfferId,
        string $clinicRegistrationReference,
        string $trustedConsumer,
        DateTimeImmutable $occurredAt,
    ): ?CommercialOfferData {
        if (! $this->trustedConsumers->trusts($trustedConsumer)) {
            throw new UntrustedCommercialOfferConsumerException('Commercial Offer consumer is not trusted.');
        }

        $offer = $this->offers->findInitialAcquisitionForRegistration(
            new CommercialOfferId($commercialOfferId),
            new ClinicRegistrationReference($clinicRegistrationReference),
        );

        if ($offer === null || $offer->isExpiredAt($occurredAt)) {
            return null;
        }

        return $this->data->fromDomain($offer);
    }

    public function claim(ClaimCommercialOfferCommand $command): CommercialOfferData
    {
        if (! $this->trustedConsumers->trusts($command->trustedConsumer)) {
            throw new UntrustedCommercialOfferConsumerException('Commercial Offer consumer is not trusted.');
        }

        return $this->transactions->run(function () use ($command): CommercialOfferData {
            $offer = $this->offers->find(new CommercialOfferId($command->commercialOfferId));

            if ($offer === null) {
                throw new CommercialOfferNotFoundException('Commercial Offer was not found.');
            }

            if ($offer->version() !== $command->expectedVersion) {
                throw new CommercialOfferVersionMismatchException('Commercial Offer version does not match.');
            }

            $offer->claim($command->paymentId, $command->occurredAt);
            $this->offers->save($offer);
            $this->audit->recordForSystem('commercial.offer.claim', $offer, $command->occurredAt, $command->correlationId);
            $this->events->publish($offer->releaseEvents());

            return $this->data->fromDomain($offer);
        });
    }
}
