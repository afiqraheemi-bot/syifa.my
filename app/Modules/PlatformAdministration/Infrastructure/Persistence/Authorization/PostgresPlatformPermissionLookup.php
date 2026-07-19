<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization;

use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionLookupInterface;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Mappers\PlatformAuthorizationPersistenceMapper;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresPlatformPermissionLookup implements PlatformPermissionLookupInterface
{
    private const string TABLE = 'platform_permissions';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PlatformAuthorizationPersistenceMapper $mapper,
    ) {}

    public function findPermission(string $permissionKey): ?PlatformPermissionData
    {
        $row = $this->connection->table(self::TABLE)
            ->where('key', $permissionKey)
            ->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->mapper->permissionFromRow($row);
    }
}
