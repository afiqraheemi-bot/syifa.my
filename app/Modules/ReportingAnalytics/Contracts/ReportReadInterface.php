<?php

declare(strict_types=1);

namespace App\Modules\ReportingAnalytics\Contracts;

interface ReportReadInterface
{
    /** @return array<string, mixed> */
    public function clinic(string $tenantId): array;

    /** @return array<string, mixed> */
    public function designer(string $platformIdentityId): array;

    /** @return array<string, mixed> */
    public function portfolio(): array;
}
