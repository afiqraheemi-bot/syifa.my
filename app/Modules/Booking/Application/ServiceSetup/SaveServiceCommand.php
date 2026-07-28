<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\ServiceSetup;

final readonly class SaveServiceCommand
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $correlationId,
        public ?string $serviceId,
        public string $name,
        public ?string $description,
        public int $sortOrder,
        public ?int $expectedVersion,
    ) {}
}
