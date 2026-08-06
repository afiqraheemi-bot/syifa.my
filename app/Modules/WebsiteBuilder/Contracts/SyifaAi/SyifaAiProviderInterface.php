<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\SyifaAi;

use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiGenerationRequest;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiGenerationResult;

interface SyifaAiProviderInterface
{
    public function generate(SyifaAiGenerationRequest $request): SyifaAiGenerationResult;
}
