<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\ValueObjects;

use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationValueException;
use DateTimeImmutable;

final readonly class DeclarationAcceptance
{
    public function __construct(
        public string $key,
        public string $version,
        public DateTimeImmutable $acceptedAt,
    ) {
        if (! preg_match('/^[a-z0-9_.-]{2,100}$/', $key)) {
            throw new InvalidClinicRegistrationValueException('Declaration key is invalid.');
        }

        if (! preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $version)) {
            throw new InvalidClinicRegistrationValueException('Declaration version is invalid.');
        }
    }
}
