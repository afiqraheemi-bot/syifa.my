<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence\Queries;

use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewReadInterface;
use App\Modules\ClinicRegistration\Contracts\Review\RegistrationReviewItemData;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;

final readonly class PostgresClinicRegistrationReviewReadAdapter implements ClinicRegistrationReviewReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function list(
        ?string $status,
        int $limit = 100,
        ?string $search = null,
        ?DateTimeImmutable $registeredFrom = null,
        ?DateTimeImmutable $registeredBefore = null,
        string $scope = 'active',
    ): array {
        if (! Schema::hasTable('clinic_registrations') || ! Schema::hasTable('clinic_registration_decisions')) {
            return [];
        }

        $normalizedSearch = $search === null ? '' : trim($search);
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
                'registrations.created_at',
                'registrations.submitted_at',
                'registrations.version',
                'registrations.archived_at',
                'decisions.outcome as decision_outcome',
                'decisions.reason_category as decision_reason_category',
                'decisions.correction_instructions',
            ])
            ->when($status !== null && $status !== '', fn ($builder) => $builder->where('registrations.status', $status))
            ->when($normalizedSearch !== '', function ($builder) use ($normalizedSearch): void {
                $needle = '%'.mb_strtolower($normalizedSearch).'%';
                $builder->where(function ($searchQuery) use ($needle): void {
                    $searchQuery
                        ->whereRaw('LOWER(COALESCE(registrations.clinic_name, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(registrations.clinic_email, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(registrations.clinic_phone, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(registrations.id::text) LIKE ?', [$needle]);
                });
            })
            ->when($registeredFrom !== null, fn ($builder) => $builder->where('registrations.created_at', '>=', $registeredFrom))
            ->when($registeredBefore !== null, fn ($builder) => $builder->where('registrations.created_at', '<', $registeredBefore))
            ->when($scope === 'active', fn ($builder) => $builder->whereNull('registrations.archived_at'))
            ->when($scope === 'archived', fn ($builder) => $builder->whereNotNull('registrations.archived_at'))
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
                self::instant($row->created_at),
                $row->submitted_at instanceof DateTimeInterface
                    ? $row->submitted_at->format(DateTimeInterface::ATOM)
                    : ($row->submitted_at === null ? null : (string) $row->submitted_at),
                (int) $row->version,
                $row->decision_outcome === null ? null : (string) $row->decision_outcome,
                $row->decision_reason_category === null ? null : (string) $row->decision_reason_category,
                $row->correction_instructions === null ? null : (string) $row->correction_instructions,
                $row->archived_at === null ? null : self::instant($row->archived_at),
                $row->archived_at === null && in_array((string) $row->status, [
                    'draft',
                    'submitted',
                    'under_review',
                    'correction_requested',
                ], true),
                $row->archived_at === null && in_array((string) $row->status, [
                    'draft',
                    'rejected',
                    'cancelled',
                    'expired',
                ], true),
            ),
            $query->get()->all(),
        ));
    }

    private static function instant(mixed $value): string
    {
        return $value instanceof DateTimeInterface
            ? $value->format(DateTimeInterface::ATOM)
            : (string) $value;
    }
}
