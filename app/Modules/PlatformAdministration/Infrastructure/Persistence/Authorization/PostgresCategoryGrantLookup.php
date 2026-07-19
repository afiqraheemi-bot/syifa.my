<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization;

use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantData;
use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantLookupInterface;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Mappers\PlatformAuthorizationPersistenceMapper;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresCategoryGrantLookup implements CategoryGrantLookupInterface
{
    private const string GRANTS_TABLE = 'platform_category_grants';

    private const string GRANT_PERMISSIONS_TABLE = 'platform_category_grant_permissions';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PlatformAuthorizationPersistenceMapper $mapper,
    ) {}

    public function findGrant(string $administratorId, string $categoryKey): ?CategoryGrantData
    {
        if (! $this->mapper->isUuid($administratorId)) {
            return null;
        }

        $row = $this->connection->table(self::GRANTS_TABLE)
            ->where('administrator_id', $administratorId)
            ->where('category_key', $categoryKey)
            ->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        $permissionKeys = array_values($this->connection->table(self::GRANT_PERMISSIONS_TABLE)
            ->where('grant_id', $row->id)
            ->orderBy('permission_key')
            ->pluck('permission_key')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all());

        return $this->mapper->grantFromRow($row, $permissionKeys);
    }
}
