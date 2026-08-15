<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Dashboard;

interface PendingWebsiteDesignerTasksReadInterface
{
    public function countPendingFor(string $platformIdentityId): int;

    /**
     * @return list<array{id: string, clinic_name: string, status: string, pending_tasks: int}>
     */
    public function recentPendingFor(string $platformIdentityId, int $limit): array;
}
