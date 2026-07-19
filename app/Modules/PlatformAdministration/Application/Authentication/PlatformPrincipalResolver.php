<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityRole;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityStatus;
use DateTimeImmutable;

final readonly class PlatformPrincipalResolver implements PlatformPrincipalResolverInterface
{
    public function __construct(
        private PlatformSessionStoreInterface $sessions,
        private PlatformIdentityLookupInterface $identities,
    ) {}

    public function resolve(DateTimeImmutable $resolvedAt): ?PlatformPrincipal
    {
        $session = $this->sessions->current($resolvedAt);

        if ($session === null) {
            return null;
        }

        $identity = $this->identities->findById($session->principal->platformIdentityId);

        if ($identity === null || $identity->status !== PlatformIdentityStatus::Active->value) {
            $this->sessions->invalidate();

            return null;
        }

        if (! in_array($identity->role, [
            PlatformIdentityRole::WebsiteDesigner->value,
            PlatformIdentityRole::SuperAdmin->value,
        ], true)) {
            $this->sessions->invalidate();

            return null;
        }

        $this->sessions->updateLastActivity($resolvedAt);

        return new PlatformPrincipal(
            $identity->id,
            $identity->role,
            $identity->name,
        );
    }
}
