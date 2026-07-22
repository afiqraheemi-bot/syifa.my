<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class ContactSectionRenderModel implements SectionRenderContract
{
    /** @param array<string, string> $socialLinks */
    public function __construct(public string $contactEmail, public string $contactPhone, public string $address, public array $socialLinks) {}

    public function type(): string
    {
        return 'CONTACT';
    }
}
