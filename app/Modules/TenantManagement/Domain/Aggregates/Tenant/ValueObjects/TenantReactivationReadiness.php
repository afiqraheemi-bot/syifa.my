<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects;

final readonly class TenantReactivationReadiness
{
    public function __construct(
        public bool $subscriptionValidated,
        public bool $domainValidated,
        public bool $ownerValidated,
        public bool $assignmentsValidated,
        public bool $pendingWorkValidated,
    ) {}

    public function isSatisfied(): bool
    {
        return $this->subscriptionValidated
            && $this->domainValidated
            && $this->ownerValidated
            && $this->assignmentsValidated
            && $this->pendingWorkValidated;
    }
}
