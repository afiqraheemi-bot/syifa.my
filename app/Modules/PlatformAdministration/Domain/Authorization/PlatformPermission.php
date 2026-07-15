<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionStatus;

final readonly class PlatformPermission
{
    public function __construct(
        public PlatformPermissionKey $key,
        public PlatformCategoryKey $categoryKey,
        public string $name,
        public string $description,
        public PlatformPermissionStatus $status,
    ) {
        if ($name === '' || trim($name) !== $name || mb_strlen($name) > 100) {
            throw new InvalidPlatformAuthorizationValueException(
                'Platform Permission name must be a normalized value of at most 100 characters.',
            );
        }

        if ($description === '' || trim($description) !== $description || mb_strlen($description) > 1000) {
            throw new InvalidPlatformAuthorizationValueException(
                'Platform Permission description must be a normalized value of at most 1000 characters.',
            );
        }
    }

    public function isActive(): bool
    {
        return $this->status === PlatformPermissionStatus::Active;
    }

    public function belongsToCategory(PlatformCategoryKey $categoryKey): bool
    {
        return $this->categoryKey->value === $categoryKey->value;
    }

    public function retire(): self
    {
        if ($this->status === PlatformPermissionStatus::Retired) {
            throw new InvalidPlatformAuthorizationValueException('Platform Permission is already retired.');
        }

        return new self($this->key, $this->categoryKey, $this->name, $this->description, PlatformPermissionStatus::Retired);
    }
}
