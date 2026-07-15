<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryStatus;

final readonly class PlatformCategory
{
    public function __construct(
        public PlatformCategoryKey $key,
        public string $name,
        public string $description,
        public PlatformCategoryStatus $status,
    ) {
        if ($name === '' || trim($name) !== $name || mb_strlen($name) > 100) {
            throw new InvalidPlatformAuthorizationValueException(
                'Platform Category name must be a normalized value of at most 100 characters.',
            );
        }

        if ($description === '' || trim($description) !== $description || mb_strlen($description) > 1000) {
            throw new InvalidPlatformAuthorizationValueException(
                'Platform Category description must be a normalized value of at most 1000 characters.',
            );
        }
    }

    public function isActive(): bool
    {
        return $this->status === PlatformCategoryStatus::Active;
    }

    public function retire(): self
    {
        if ($this->status === PlatformCategoryStatus::Retired) {
            throw new InvalidPlatformAuthorizationValueException('Platform Category is already retired.');
        }

        return new self($this->key, $this->name, $this->description, PlatformCategoryStatus::Retired);
    }
}
