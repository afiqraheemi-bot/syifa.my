<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization;

use App\Modules\PlatformAdministration\Contracts\Authorization\Exceptions\AmbiguousPlatformAdministratorProfileException;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorLookupInterface;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Mappers\PlatformAuthorizationPersistenceMapper;
use Illuminate\Database\ConnectionInterface;

final class PostgresPlatformAdministratorLookup implements PlatformAdministratorLookupInterface
{
    private const string TABLE = 'platform_administrators';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PlatformAuthorizationPersistenceMapper $mapper,
    ) {}

    public function findByPlatformIdentityId(string $platformIdentityId): ?PlatformAdministratorData
    {
        if (! $this->mapper->isUuid($platformIdentityId)) {
            return null;
        }

        $rows = $this->connection->table(self::TABLE)
            ->where('platform_identity_id', $platformIdentityId)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        if ($rows->count() > 1) {
            throw new AmbiguousPlatformAdministratorProfileException(
                'More than one Platform Administrator profile exists for the same Platform Identity.',
            );
        }

        return $this->mapper->administratorFromRow($rows->first());
    }
}
