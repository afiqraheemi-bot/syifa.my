<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Application;

use App\Modules\AcquisitionOffer\Application\Exceptions\CommercialOfferNotFoundException;
use App\Modules\AcquisitionOffer\Contracts\Data\CommercialOfferData;
use App\Modules\AcquisitionOffer\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferId;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PlatformIdentityReference;

final readonly class GetCommercialOfferService
{
    public function __construct(
        private CommercialOfferRepositoryInterface $offers,
        private CommercialOfferDataAssembler $data,
    ) {}

    public function execute(string $platformIdentityId, string $commercialOfferId): CommercialOfferData
    {
        $offer = $this->offers->find(new CommercialOfferId($commercialOfferId));

        if ($offer === null) {
            throw new CommercialOfferNotFoundException('Commercial Offer was not found.');
        }

        $offer->assertOwnedBy(new PlatformIdentityReference($platformIdentityId));

        return $this->data->fromDomain($offer);
    }
}
