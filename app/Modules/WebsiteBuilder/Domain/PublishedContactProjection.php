<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class PublishedContactProjection
{
    /**
     * @param  array<string, string>  $socialLinks
     * @param  list<PublishedBusinessHour>  $businessHours
     */
    public function __construct(
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public array $socialLinks,
        public array $businessHours = [],
        public ?string $whatsAppNumber = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
        if (($latitude === null) !== ($longitude === null)) {
            throw new InvalidWebsiteValueException('Published Contact coordinates must be complete.');
        }
    }

    public function hasMinimumContact(): bool
    {
        return $this->phone !== null || $this->email !== null;
    }
}
