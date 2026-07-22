<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class ContactSectionRenderModel implements SectionRenderContract
{
    /**
     * @param  array<string, string>  $socialLinks
     * @param  list<BusinessHourRenderModel>  $businessHours
     */
    public function __construct(
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $address,
        public array $socialLinks,
        public array $businessHours,
        public ?string $whatsAppNumber,
        public ?float $latitude,
        public ?float $longitude,
    ) {}

    public function type(): string
    {
        return 'CONTACT';
    }
}
