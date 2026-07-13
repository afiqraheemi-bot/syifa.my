<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\PlatformIdentity;

use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentity;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityRole;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityStatus;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityEmail;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityName;

final readonly class GetPlatformIdentityService
{
    public function __construct(private PlatformIdentityLookupInterface $identities) {}

    public function execute(PlatformIdentityId $platformIdentityId): ?PlatformIdentity
    {
        $identity = $this->identities->findById($platformIdentityId->value);

        if ($identity === null) {
            return null;
        }

        return new PlatformIdentity(
            id: new PlatformIdentityId($identity->id),
            email: new PlatformIdentityEmail($identity->email),
            name: new PlatformIdentityName($identity->name),
            role: PlatformIdentityRole::from($identity->role),
            status: PlatformIdentityStatus::from($identity->status),
        );
    }
}
