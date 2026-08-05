<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class HeaderRenderModel
{
    public function __construct(public string $clinicName, public ?string $tagline, public ?string $logoAssetId, public string $logoDisplaySize = 'standard') {}
}
