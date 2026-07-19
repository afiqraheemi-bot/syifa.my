<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Mappers;

use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionData;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Exceptions\InvalidPlatformAuthorizationStorageStateException;
use DateTimeImmutable;
use DateTimeInterface;
use stdClass;

final class PlatformAuthorizationPersistenceMapper
{
    /**
     * A UUID-typed column rejects a non-UUID-shaped bind value at the database level rather
     * than simply matching zero rows. Callers must check this before querying by such a
     * column so a malformed identifier fails closed (not found) instead of surfacing a raw
     * database error.
     */
    public function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    public function administratorFromRow(stdClass $row): PlatformAdministratorData
    {
        return new PlatformAdministratorData(
            $this->stringValue($row, 'administrator_id'),
            $this->stringValue($row, 'platform_identity_id'),
            $this->stringValue($row, 'status'),
        );
    }

    public function categoryFromRow(stdClass $row): PlatformCategoryData
    {
        return new PlatformCategoryData(
            $this->stringValue($row, 'key'),
            $this->stringValue($row, 'name'),
            $this->stringValue($row, 'description'),
            $this->stringValue($row, 'status'),
        );
    }

    public function permissionFromRow(stdClass $row): PlatformPermissionData
    {
        return new PlatformPermissionData(
            $this->stringValue($row, 'key'),
            $this->stringValue($row, 'category_key'),
            $this->stringValue($row, 'name'),
            $this->stringValue($row, 'description'),
            $this->stringValue($row, 'status'),
        );
    }

    /**
     * @param  list<string>  $permissionKeys  Already ordered by the caller for deterministic membership.
     */
    public function grantFromRow(stdClass $row, array $permissionKeys): CategoryGrantData
    {
        return new CategoryGrantData(
            $this->stringValue($row, 'administrator_id'),
            $this->stringValue($row, 'category_key'),
            $permissionKeys,
            $this->stringValue($row, 'status'),
            $this->dateTimeValue($row->granted_at ?? null, 'granted_at')->format('Y-m-d\TH:i:s\Z'),
        );
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidPlatformAuthorizationStorageStateException($field.' must be a string.');
        }

        return $value;
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (! is_string($value)) {
            throw new InvalidPlatformAuthorizationStorageStateException($field.' must be an instant.');
        }

        return new DateTimeImmutable($value);
    }
}
