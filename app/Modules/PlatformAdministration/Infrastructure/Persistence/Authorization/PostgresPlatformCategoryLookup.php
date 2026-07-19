<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization;

use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryLookupInterface;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Mappers\PlatformAuthorizationPersistenceMapper;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresPlatformCategoryLookup implements PlatformCategoryLookupInterface
{
    private const string TABLE = 'platform_categories';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PlatformAuthorizationPersistenceMapper $mapper,
    ) {}

    public function findCategory(string $categoryKey): ?PlatformCategoryData
    {
        $row = $this->connection->table(self::TABLE)
            ->where('key', $categoryKey)
            ->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->mapper->categoryFromRow($row);
    }
}
