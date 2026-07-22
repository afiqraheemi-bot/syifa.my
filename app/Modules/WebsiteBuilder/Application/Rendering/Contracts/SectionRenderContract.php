<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

interface SectionRenderContract
{
    public function type(): string;
}
