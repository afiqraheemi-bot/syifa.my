<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Application;

use App\Modules\Commercial\Application\Exceptions\CommercialOfferNotFoundException;
use App\Modules\Commercial\Contracts\Data\CommercialOfferData;
use App\Modules\Commercial\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;

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
