<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Session;

use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;

final readonly class DeleteClinicOwnerSessionService
{
    public function __construct(private ClinicOwnerSessionStoreInterface $sessions) {}

    public function execute(): void
    {
        $this->sessions->invalidate();
    }
}
