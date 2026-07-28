<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\ServiceSetup;

final readonly class ServiceSetupData
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public int $sortOrder,
        public string $status,
        public int $version,
    ) {}
}
