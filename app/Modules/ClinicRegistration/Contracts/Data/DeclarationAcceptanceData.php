<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Data;

final readonly class DeclarationAcceptanceData
{
    public function __construct(
        public string $key,
        public string $version,
        public string $acceptedAt,
    ) {}
}
