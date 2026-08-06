<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\SyifaAi;

use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiUsageRecord;

interface SyifaAiUsageRepositoryInterface
{
    public function tokensUsedThisMonth(string $tenantId): int;

    public function record(SyifaAiUsageRecord $record): void;
}
