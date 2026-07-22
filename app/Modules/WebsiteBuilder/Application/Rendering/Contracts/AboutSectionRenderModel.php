<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class AboutSectionRenderModel implements SectionRenderContract
{
    public function __construct(public string $heading, public string $description, public ?string $imageAssetId) {}

    public function type(): string
    {
        return 'ABOUT';
    }
}
