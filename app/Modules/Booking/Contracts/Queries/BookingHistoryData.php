<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

final readonly class BookingHistoryData
{
    /** @param array<string, string|int|bool|null> $payload */
    public function __construct(
        public string $id,
        public string $eventType,
        public string $actorType,
        public ?string $actorId,
        public string $occurredAt,
        public array $payload,
    ) {}
}
