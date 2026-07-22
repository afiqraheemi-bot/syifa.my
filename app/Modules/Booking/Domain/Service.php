<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain;

use App\Modules\Booking\Domain\ValueObjects\ServiceDescription;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\ServiceStatus;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;

final class Service
{
    public function __construct(
        public readonly ServiceId $id,
        public readonly TenantId $tenantId,
        public ServiceName $name,
        public ?ServiceDescription $description,
        public SortOrder $sortOrder,
        private ServiceStatus $status,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private int $version = 0,
    ) {}

    public static function register(
        ServiceId $id,
        TenantId $tenantId,
        ServiceName $name,
        ?ServiceDescription $description,
        SortOrder $sortOrder,
        DateTimeImmutable $occurredAt,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            description: $description,
            sortOrder: $sortOrder,
            status: ServiceStatus::Active,
            createdAt: $occurredAt,
            updatedAt: $occurredAt,
            version: 0,
        );
    }

    public function activate(DateTimeImmutable $occurredAt): void
    {
        if ($this->status === ServiceStatus::Active) {
            return;
        }

        $this->status = ServiceStatus::Active;
        $this->updatedAt = $occurredAt;
    }

    public function deactivate(DateTimeImmutable $occurredAt): void
    {
        if ($this->status === ServiceStatus::Inactive) {
            return;
        }

        $this->status = ServiceStatus::Inactive;
        $this->updatedAt = $occurredAt;
    }

    public function status(): ServiceStatus
    {
        return $this->status;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        $this->version = $version;
    }
}
