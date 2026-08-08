<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Application;

use App\Modules\AcquisitionOffer\Contracts\Data\CommercialOfferData;
use App\Modules\AcquisitionOffer\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PlatformIdentityReference;

final readonly class ViewCurrentCommercialOfferService
{
    public function __construct(
        private CommercialOfferRepositoryInterface $offers,
        private CommercialOfferDataAssembler $data,
    ) {}

    public function execute(string $platformIdentityId): ?CommercialOfferData
    {
        $offer = $this->offers->findCurrentForPlatformIdentity(new PlatformIdentityReference($platformIdentityId));

        return $offer === null ? null : $this->data->fromDomain($offer);
    }
}
