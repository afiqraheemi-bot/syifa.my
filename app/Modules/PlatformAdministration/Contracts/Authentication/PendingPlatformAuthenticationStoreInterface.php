<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

interface PendingPlatformAuthenticationStoreInterface
{
    public function establish(PendingPlatformAuthenticationData $pending): void;

    public function current(): ?PendingPlatformAuthenticationData;

    public function clear(): void;
}
