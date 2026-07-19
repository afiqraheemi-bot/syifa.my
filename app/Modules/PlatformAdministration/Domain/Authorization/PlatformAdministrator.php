<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorId;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorStatus;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;

/**
 * A PlatformAdministration-owned authorization/governance profile, distinct from — and
 * always referencing exactly one of — the authoritative {@see PlatformIdentityId}. This is
 * never a second authentication identity: it carries no credential, session, token, or role
 * list of its own. Role standing is resolved from Platform Identity; this profile only
 * answers whether a given identity has been onboarded into Commercial Catalogue-style
 * category governance at all, and whether that governance standing is currently active.
 */
final readonly class PlatformAdministrator
{
    public function __construct(
        public PlatformAdministratorId $id,
        public PlatformIdentityId $platformIdentityId,
        public PlatformAdministratorStatus $status,
    ) {}

    public function isActive(): bool
    {
        return $this->status === PlatformAdministratorStatus::Active;
    }

    public function suspend(): self
    {
        if ($this->status === PlatformAdministratorStatus::Suspended) {
            throw new InvalidPlatformAuthorizationValueException('Platform Administrator is already suspended.');
        }

        return new self($this->id, $this->platformIdentityId, PlatformAdministratorStatus::Suspended);
    }

    public function reactivate(): self
    {
        if ($this->status === PlatformAdministratorStatus::Active) {
            throw new InvalidPlatformAuthorizationValueException('Platform Administrator is already active.');
        }

        return new self($this->id, $this->platformIdentityId, PlatformAdministratorStatus::Active);
    }
}
