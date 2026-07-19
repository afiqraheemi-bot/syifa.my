<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\PlatformIdentity;

use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\Exceptions\InvalidPlatformIdentityIdException;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\PlatformIdentity\Mappers\PlatformIdentityPersistenceMapper;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresPlatformIdentityLookup implements PlatformIdentityLookupInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PlatformIdentityPersistenceMapper $mapper,
    ) {}

    public function findById(string $platformIdentityId): ?PlatformIdentityData
    {
        try {
            $validated = new PlatformIdentityId($platformIdentityId);
        } catch (InvalidPlatformIdentityIdException) {
            return null;
        }

        $row = $this->connection
            ->table('platform_workforce_credentials')
            ->select([
                'platform_identity_id',
                'normalized_email',
                'name',
                'role',
                'account_status',
            ])
            ->where('platform_identity_id', $validated->value)
            ->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->mapper->toData($row);
    }
}
