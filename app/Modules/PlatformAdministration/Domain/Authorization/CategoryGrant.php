<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidCategoryGrantException;
use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\CategoryGrantStatus;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorId;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionKey;
use DateTimeImmutable;

/**
 * A lightweight, immutable record connecting a Platform Administrator to a set of
 * Permissions within a single Platform Category. It exposes only its own state —
 * it does not evaluate authorization. See {@see PlatformAuthorizationService}.
 */
final readonly class CategoryGrant
{
    /** @var list<PlatformPermissionKey> */
    public array $permissionKeys;

    /** @param list<PlatformPermissionKey> $permissionKeys */
    public function __construct(
        public PlatformAdministratorId $administratorId,
        public PlatformCategoryKey $categoryKey,
        array $permissionKeys,
        public CategoryGrantStatus $status,
        public DateTimeImmutable $grantedAt,
    ) {
        if ($permissionKeys === []) {
            throw new InvalidCategoryGrantException('A Category Grant must include at least one Permission.');
        }

        $values = array_map(static fn (PlatformPermissionKey $key): string => $key->value, $permissionKeys);
        if (count($values) !== count(array_unique($values))) {
            throw new InvalidCategoryGrantException('Category Grant permission keys must be unique.');
        }

        $this->permissionKeys = $permissionKeys;
    }

    public function isActive(): bool
    {
        return $this->status === CategoryGrantStatus::Active;
    }

    public function hasPermission(PlatformPermissionKey $permission): bool
    {
        foreach ($this->permissionKeys as $granted) {
            if ($granted->value === $permission->value) {
                return true;
            }
        }

        return false;
    }

    public function belongsToCategory(PlatformCategoryKey $categoryKey): bool
    {
        return $this->categoryKey->value === $categoryKey->value;
    }

    public function revoke(): self
    {
        if ($this->status === CategoryGrantStatus::Revoked) {
            throw new InvalidPlatformAuthorizationValueException('Category Grant is already revoked.');
        }

        return new self($this->administratorId, $this->categoryKey, $this->permissionKeys, CategoryGrantStatus::Revoked, $this->grantedAt);
    }
}
