<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Persistence\Queries;

use App\Modules\TenantManagement\Contracts\ClinicOwner\ClinicOwnerLocalePreferenceReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresClinicOwnerLocalePreferenceReadAdapter implements ClinicOwnerLocalePreferenceReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forTenant(string $tenantId): ?string
    {
        $value = $this->connection->table('clinic_owner_authorities')
            ->where('tenant_id', $tenantId)
            ->where('authority_status', 'active')
            ->orderBy('created_at')
            ->value('preferred_locale');

        return in_array($value, ['en', 'ms'], true) ? $value : null;
    }
}
