<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class DeclarationAcceptanceStorageRecord
{
    public function __construct(
        public string $registrationId,
        public string $declarationKey,
        public string $declarationVersion,
        public DateTimeImmutable $acceptedAt,
    ) {}
}
