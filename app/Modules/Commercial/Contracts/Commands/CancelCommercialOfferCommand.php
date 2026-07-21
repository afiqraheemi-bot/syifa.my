<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Commands;

use DateTimeImmutable;

final readonly class CancelCommercialOfferCommand
{
    public function __construct(
        public string $platformIdentityId,
        public string $commercialOfferId,
        public int $expectedVersion,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
