<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\SyifaAi;

interface SyifaAiUsageReadInterface
{
    public function summary(string $asOfDate): SyifaAiUsageSummaryData;

    /** @return list<SyifaAiCapabilityUsageData> */
    public function byCapability(string $asOfDate): array;

    /** @return list<SyifaAiEngineUsageData> */
    public function byEngine(string $asOfDate): array;

    /** @return list<SyifaAiTenantUsageData> */
    public function topTenants(string $asOfDate, int $limit): array;
}
