<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use DateTimeImmutable;

final class WebsiteSection
{
    public function __construct(
        public readonly SectionId $id,
        public readonly SectionType $type,
        private SectionDisplayOrder $displayOrder,
        private bool $enabled,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private int $version = 0,
    ) {
        if ($version < 0 || $updatedAt < $createdAt) {
            throw new InvalidWebsiteValueException('Website Section persisted state is invalid.');
        }
    }

    public static function create(SectionId $id, SectionType $type, SectionDisplayOrder $order, DateTimeImmutable $at): self
    {
        return new self($id, $type, $order, true, $at, $at);
    }

    public function displayOrder(): SectionDisplayOrder
    {
        return $this->displayOrder;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function setEnabled(bool $enabled, DateTimeImmutable $at): void
    {
        if ($this->enabled === $enabled) {
            return;
        }
        $this->enabled = $enabled;
        $this->updatedAt = $at;
    }

    public function moveTo(SectionDisplayOrder $order, DateTimeImmutable $at): void
    {
        if ($this->displayOrder->value === $order->value) {
            return;
        }
        $this->displayOrder = $order;
        $this->updatedAt = $at;
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version < 1) {
            throw new InvalidWebsiteValueException('Persisted Website Section version must be positive.');
        }
        $this->version = $version;
    }
}
