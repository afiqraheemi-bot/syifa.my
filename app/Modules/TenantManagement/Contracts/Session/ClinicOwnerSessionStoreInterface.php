<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Session;

interface ClinicOwnerSessionStoreInterface
{
    public function establish(ClinicOwnerSessionState $state, bool $remember = false): void;

    public function current(): ?ClinicOwnerSessionState;

    public function updateLastActivity(\DateTimeImmutable $lastActivityAt): void;

    public function invalidate(): void;
}
