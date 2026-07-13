<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Session;

interface ClinicOwnerSessionStoreInterface
{
    public function establish(ClinicOwnerSessionState $state): void;

    public function current(): ?ClinicOwnerSessionState;

    public function updateLastActivity(\DateTimeImmutable $lastActivityAt): void;

    public function invalidate(): void;
}
