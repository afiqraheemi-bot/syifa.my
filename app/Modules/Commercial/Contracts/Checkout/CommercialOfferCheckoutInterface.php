<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Checkout;

use App\Modules\Commercial\Contracts\Commands\MarkCommercialOfferConsumedCommand;
use App\Modules\Commercial\Contracts\Data\CommercialOfferData;
use DateTimeImmutable;

interface CommercialOfferCheckoutInterface
{
    public function offerForCheckout(string $commercialOfferId, string $trustedConsumer, DateTimeImmutable $occurredAt): ?CommercialOfferData;

    public function markConsumed(MarkCommercialOfferConsumedCommand $command): CommercialOfferData;
}
