<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence\Queries;

use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewReadInterface;
use App\Modules\ClinicRegistration\Contracts\Review\RegistrationReviewItemData;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresClinicRegistrationReviewReadAdapter implements ClinicRegistrationReviewReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function list(?string $status, int $limit = 100): array
    {
        $query = $this->connection->table('clinic_registrations as registrations')
            ->leftJoin('clinic_registration_decisions as decisions', function ($join): void {
                $join->on('decisions.clinic_registration_id', '=', 'registrations.id')
                    ->whereNull('decisions.superseded_at');
            })
            ->select([
                'registrations.id',
                'registrations.status',
                'registrations.clinic_name',
                'registrations.clinic_email',
                'registrations.clinic_phone',
                'registrations.clinic_address',
                'registrations.submitted_at',
                'registrations.version',
                'decisions.outcome as decision_outcome',
                'decisions.reason_category as decision_reason_category',
                'decisions.correction_instructions',
            ])
            ->when($status !== null && $status !== '', fn ($builder) => $builder->where('registrations.status', $status))
            ->orderByRaw('registrations.submitted_at DESC NULLS LAST')
            ->orderByDesc('registrations.created_at')
            ->limit(max(1, min($limit, 100)));

        return array_values(array_map(
            static fn ($row): RegistrationReviewItemData => new RegistrationReviewItemData(
                (string) $row->id,
                (string) $row->status,
                $row->clinic_name === null ? null : (string) $row->clinic_name,
                $row->clinic_email === null ? null : (string) $row->clinic_email,
                $row->clinic_phone === null ? null : (string) $row->clinic_phone,
                $row->clinic_address === null ? null : (string) $row->clinic_address,
                $row->submitted_at instanceof DateTimeInterface
                    ? $row->submitted_at->format(DateTimeInterface::ATOM)
                    : ($row->submitted_at === null ? null : (string) $row->submitted_at),
                (int) $row->version,
                $row->decision_outcome === null ? null : (string) $row->decision_outcome,
                $row->decision_reason_category === null ? null : (string) $row->decision_reason_category,
                $row->correction_instructions === null ? null : (string) $row->correction_instructions,
            ),
            $query->get()->all(),
        ));
    }
}
