<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Application;

use App\Modules\AcquisitionOffer\Application\Audit\CommercialOfferAuditTrail;
use App\Modules\AcquisitionOffer\Application\Exceptions\CommercialOfferNotFoundException;
use App\Modules\AcquisitionOffer\Application\Exceptions\CommercialOfferVersionMismatchException;
use App\Modules\AcquisitionOffer\Contracts\Commands\CancelCommercialOfferCommand;
use App\Modules\AcquisitionOffer\Contracts\Data\CommercialOfferData;
use App\Modules\AcquisitionOffer\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\AcquisitionOffer\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\AcquisitionOffer\Contracts\Transactions\CommercialTransactionInterface;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferId;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PlatformIdentityReference;

final readonly class CancelCommercialOfferService
{
    public function __construct(
        private CommercialOfferRepositoryInterface $offers,
        private CommercialOfferDataAssembler $data,
        private CommercialOfferAuditTrail $audit,
        private CommercialOfferEventPublisherInterface $events,
        private CommercialTransactionInterface $transactions,
    ) {}

    public function execute(CancelCommercialOfferCommand $command): CommercialOfferData
    {
        return $this->transactions->run(function () use ($command): CommercialOfferData {
            $offer = $this->offers->find(new CommercialOfferId($command->commercialOfferId));

            if ($offer === null) {
                throw new CommercialOfferNotFoundException('Commercial Offer was not found.');
            }

            if ($offer->version() !== $command->expectedVersion) {
                throw new CommercialOfferVersionMismatchException('Commercial Offer version does not match.');
            }

            $offer->cancel(new PlatformIdentityReference($command->platformIdentityId), $command->occurredAt);
            $this->offers->save($offer);
            $this->audit->recordForPlatformIdentity('commercial.offer.cancel', $offer, $command->occurredAt, $command->correlationId);
            $this->events->publish($offer->releaseEvents());

            return $this->data->fromDomain($offer);
        });
    }
}
