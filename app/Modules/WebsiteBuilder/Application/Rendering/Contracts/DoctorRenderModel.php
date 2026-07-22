<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class DoctorRenderModel
{
    public function __construct(public string $name, public ?string $professionalTitle, public ?string $photoAssetId) {}
}
