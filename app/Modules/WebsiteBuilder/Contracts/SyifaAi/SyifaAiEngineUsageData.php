<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\SyifaAi;

final readonly class SyifaAiEngineUsageData
{
    public function __construct(
        public string $model,
        public int $requests,
        public int $tokens,
    ) {}
}
