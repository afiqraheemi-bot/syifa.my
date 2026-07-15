<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorId;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorStatus;

final readonly class PlatformAdministrator
{
    public function __construct(
        public PlatformAdministratorId $id,
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

        return new self($this->id, PlatformAdministratorStatus::Suspended);
    }

    public function reactivate(): self
    {
        if ($this->status === PlatformAdministratorStatus::Active) {
            throw new InvalidPlatformAuthorizationValueException('Platform Administrator is already active.');
        }

        return new self($this->id, PlatformAdministratorStatus::Active);
    }
}
