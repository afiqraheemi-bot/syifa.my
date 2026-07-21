<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\Commercial\Contracts\Checkout\CommercialOfferCheckoutInterface;
use App\Modules\Commercial\Contracts\Commands\ClaimCommercialOfferCommand;
use App\Modules\Commercial\Contracts\Data\CommercialOfferData;
use DateTimeImmutable;

final readonly class ClaimCommercialOfferService
{
    public function __construct(private CommercialOfferCheckoutInterface $checkout) {}

    public function execute(CommercialOfferData $offer, string $paymentId, DateTimeImmutable $occurredAt, string $correlationId): CommercialOfferData
    {
        return $this->checkout->claim(new ClaimCommercialOfferCommand(
            commercialOfferId: $offer->id,
            paymentId: $paymentId,
            trustedConsumer: 'payment',
            expectedVersion: $offer->version,
            occurredAt: $occurredAt,
            correlationId: $correlationId,
        ));
    }
}
