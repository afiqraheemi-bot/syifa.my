<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;

final readonly class LogoutPlatformSessionService
{
    public function __construct(private PlatformSessionStoreInterface $sessions) {}

    public function execute(): void
    {
        $this->sessions->invalidate();
    }
}
