<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class BrandingRenderModel
{
    public function __construct(public string $clinicName, public ?string $tagline, public string $primaryColor, public string $secondaryColor, public ?string $logoAssetId, public ?string $faviconAssetId, public string $logoDisplaySize = 'standard', public string $whatsAppButtonStyle = 'pill') {}
}
