<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Application;

use App\Modules\Commercial\Contracts\Data\CommercialOfferData;
use App\Modules\Commercial\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;

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
