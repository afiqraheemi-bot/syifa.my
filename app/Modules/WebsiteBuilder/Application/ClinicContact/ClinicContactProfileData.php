<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicContact;

use App\Modules\WebsiteBuilder\Domain\Clinic;

final readonly class ClinicContactProfileData
{
    private function __construct(
        public string $clinicId,
        public int $version,
        public ?string $operationalPhone,
        public ?string $operationalEmail,
        public ?string $postalAddress,
        public ?string $whatsAppNumber,
        public ?float $latitude,
        public ?float $longitude,
    ) {}

    public static function fromClinic(Clinic $clinic): self
    {
        $profile = $clinic->contactProfile();

        return new self(
            $clinic->id->value,
            $clinic->version(),
            $profile->operationalPhone,
            $profile->operationalEmail,
            $profile->postalAddress,
            $profile->whatsAppNumber,
            $profile->latitude,
            $profile->longitude,
        );
    }

    /** @return array<string, int|string|float|null> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'operational_phone' => $this->operationalPhone,
            'operational_email' => $this->operationalEmail,
            'postal_address' => $this->postalAddress,
            'whatsapp_number' => $this->whatsAppNumber,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
