<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\SyifaAi;

final readonly class SyifaAiUsageSummaryData
{
    public function __construct(
        public int $totalRequests,
        public int $failedRequests,
        public int $inputTokens,
        public int $outputTokens,
        public int $activeTenants,
    ) {}
}
