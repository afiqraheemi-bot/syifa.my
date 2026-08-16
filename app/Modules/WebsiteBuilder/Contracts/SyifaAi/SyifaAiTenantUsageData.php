<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\SyifaAi;

final readonly class SyifaAiTenantUsageData
{
    public function __construct(
        public string $tenantId,
        public ?string $clinicName,
        public int $requests,
        public int $tokens,
    ) {}
}
