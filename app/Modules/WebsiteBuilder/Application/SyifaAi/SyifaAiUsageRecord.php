<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\SyifaAi;

final readonly class SyifaAiUsageRecord
{
    public function __construct(
        public string $tenantId,
        public string $websiteId,
        public string $actorId,
        public SyifaAiCapability $capability,
        public ?SyifaAiSection $section,
        public string $model,
        public string $status,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
