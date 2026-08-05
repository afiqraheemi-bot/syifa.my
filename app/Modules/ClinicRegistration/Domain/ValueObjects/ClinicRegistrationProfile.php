<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\ValueObjects;

use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationValueException;

final readonly class ClinicRegistrationProfile
{
    public function __construct(
        public ?string $clinicName,
        public ?string $clinicEmail,
        public ?string $clinicPhone,
        public ?string $clinicAddress,
        public ?string $preferredSubdomain = null,
        public ?string $selectedWebsiteTemplate = null,
    ) {
        foreach ([
            'clinic name' => [$clinicName, 200],
            'clinic email' => [$clinicEmail, 254],
            'clinic phone' => [$clinicPhone, 40],
            'clinic address' => [$clinicAddress, 1000],
        ] as $label => [$value, $maximumLength]) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidClinicRegistrationValueException(sprintf('%s must not be blank.', ucfirst((string) $label)));
            }

            if ($value !== null && mb_strlen($value) > $maximumLength) {
                throw new InvalidClinicRegistrationValueException(sprintf('%s is too long.', ucfirst((string) $label)));
            }
        }

        if ($preferredSubdomain !== null
            && preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/', $preferredSubdomain) !== 1) {
            throw new InvalidClinicRegistrationValueException('Preferred Website subdomain is invalid.');
        }

        if ($selectedWebsiteTemplate !== null && ! in_array($selectedWebsiteTemplate, [
            'SYIFA_ESSENTIAL',
            'SYIFA_CARE',
            'SYIFA_DENTAL',
            'SYIFA_AESTHETIC',
            'SYIFA_SPECIALIST',
        ], true)) {
            throw new InvalidClinicRegistrationValueException('Selected Website template is invalid.');
        }
    }

    public function isSubmittable(): bool
    {
        return $this->clinicName !== null
            && $this->clinicEmail !== null
            && $this->clinicPhone !== null
            && $this->clinicAddress !== null;
    }
}
