<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Commands;

use DateTimeImmutable;

final readonly class MarkCommercialOfferConsumedCommand
{
    public function __construct(
        public string $commercialOfferId,
        public string $trustedConsumer,
        public int $expectedVersion,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
