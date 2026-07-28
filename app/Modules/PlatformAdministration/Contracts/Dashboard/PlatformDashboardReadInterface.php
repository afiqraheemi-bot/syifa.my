<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Dashboard;

interface PlatformDashboardReadInterface
{
    public function overview(): PlatformDashboardData;
}
