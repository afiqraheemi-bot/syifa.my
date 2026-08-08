<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\Checkout;

use App\Modules\AcquisitionOffer\Contracts\Commands\ClaimCommercialOfferCommand;
use App\Modules\AcquisitionOffer\Contracts\Data\CommercialOfferData;
use DateTimeImmutable;

interface CommercialOfferCheckoutInterface
{
    public function offerForCheckout(string $commercialOfferId, string $trustedConsumer, DateTimeImmutable $occurredAt): ?CommercialOfferData;

    public function initialAcquisitionOfferForCheckout(
        string $commercialOfferId,
        string $clinicRegistrationReference,
        string $trustedConsumer,
        DateTimeImmutable $occurredAt,
    ): ?CommercialOfferData;

    public function claim(ClaimCommercialOfferCommand $command): CommercialOfferData;
}
