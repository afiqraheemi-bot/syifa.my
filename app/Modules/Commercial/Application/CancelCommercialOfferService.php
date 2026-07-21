<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Application;

use App\Modules\Commercial\Application\Audit\CommercialOfferAuditTrail;
use App\Modules\Commercial\Application\Exceptions\CommercialOfferNotFoundException;
use App\Modules\Commercial\Application\Exceptions\CommercialOfferVersionMismatchException;
use App\Modules\Commercial\Contracts\Commands\CancelCommercialOfferCommand;
use App\Modules\Commercial\Contracts\Data\CommercialOfferData;
use App\Modules\Commercial\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\Commercial\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\Commercial\Contracts\Transactions\CommercialTransactionInterface;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;

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
