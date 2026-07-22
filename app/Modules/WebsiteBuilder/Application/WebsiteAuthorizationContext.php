<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application;

final readonly class WebsiteAuthorizationContext
{
    public function __construct(
        public string $actorId,
        public string $role,
        public ?string $actorTenantId = null,
        public ?string $assignedTenantId = null,
        public bool $supportAuthorized = false,
    ) {}
}
