<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class HeroSectionRenderModel implements SectionRenderContract
{
    public function __construct(public string $headline, public ?string $subheadline, public ?string $primaryCtaLabel, public ?string $primaryCtaTarget, public ?string $secondaryCtaLabel, public ?string $secondaryCtaTarget, public ?string $heroImageAssetId) {}

    public function type(): string
    {
        return 'HERO';
    }
}
