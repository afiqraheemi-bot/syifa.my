<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class FooterRenderModel
{
    /** @param array<string, string> $socialLinks */
    public function __construct(public string $clinicName, public string $contactEmail, public string $contactPhone, public string $address, public array $socialLinks) {}
}
