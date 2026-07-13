<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\PlatformIdentity;

use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityEmail;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityName;

final readonly class PlatformIdentity
{
    public function __construct(
        public PlatformIdentityId $id,
        public PlatformIdentityEmail $email,
        public PlatformIdentityName $name,
        public PlatformIdentityRole $role,
        public PlatformIdentityStatus $status,
    ) {}

    public function isActive(): bool
    {
        return $this->status === PlatformIdentityStatus::Active;
    }

    public function isWebsiteDesigner(): bool
    {
        return $this->role === PlatformIdentityRole::WebsiteDesigner;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === PlatformIdentityRole::SuperAdmin;
    }
}
