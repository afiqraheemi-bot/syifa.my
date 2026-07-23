<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Queries;

use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresClinicSummaryReadAdapter implements ClinicSummaryReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function summary(string $trustedTenantId): ?ClinicSummaryData
    {
        $row = $this->connection->table('clinics')
            ->leftJoin('clinic_contact_profiles', function ($join): void {
                $join->on('clinic_contact_profiles.clinic_id', '=', 'clinics.id')
                    ->on('clinic_contact_profiles.tenant_id', '=', 'clinics.tenant_id');
            })
            ->where('clinics.tenant_id', $trustedTenantId)
            ->first([
                'clinics.id',
                'clinics.timezone',
                'clinic_contact_profiles.operational_phone',
                'clinic_contact_profiles.operational_email',
                'clinic_contact_profiles.postal_address',
            ]);

        if ($row === null) {
            return null;
        }

        $configured = $row->operational_phone !== null
            || $row->operational_email !== null
            || $row->postal_address !== null;

        return new ClinicSummaryData((string) $row->id, (string) $row->timezone, $configured);
    }
}
