<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\SyifaAi;

final readonly class SyifaAiCapabilityUsageData
{
    public function __construct(
        public string $capability,
        public int $requests,
        public int $tokens,
    ) {}
}
