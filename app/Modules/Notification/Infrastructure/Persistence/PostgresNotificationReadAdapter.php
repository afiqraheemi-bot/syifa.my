<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Persistence;

use App\Modules\Notification\Contracts\NotificationReadInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;

final readonly class PostgresNotificationReadAdapter implements NotificationReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forTenant(string $tenantId, ?string $status, ?string $triggerType): array
    {
        return $this->read($tenantId, $status, $triggerType);
    }

    public function forPlatform(?string $tenantId, ?string $status, ?string $triggerType): array
    {
        return $this->read($tenantId, $status, $triggerType, $tenantId === null);
    }

    /** @return array{entries: list<array<string, mixed>>} */
    private function read(
        ?string $tenantId,
        ?string $status,
        ?string $triggerType,
        bool $allTenants = false,
    ): array {
        $query = $this->connection->table('notifications');
        if (! $allTenants) {
            $query->where('tenant_id', $tenantId);
        }
        $this->filters($query, $status, $triggerType);

        $entries = $query->orderByDesc('created_at')->limit(100)->get()->map(
            function (stdClass $row): array {
                $attempts = $this->connection->table('notification_delivery_attempts')
                    ->where('notification_id', $row->notification_id)
                    ->orderBy('sequence')
                    ->get(['sequence', 'attempted_at', 'outcome', 'retry_eligible', 'reason_code'])
                    ->map(static fn (stdClass $attempt): array => [
                        'sequence' => (int) $attempt->sequence,
                        'attemptedAt' => (string) $attempt->attempted_at,
                        'outcome' => (string) $attempt->outcome,
                        'retryEligible' => (bool) $attempt->retry_eligible,
                        'reasonCode' => $attempt->reason_code === null ? null : (string) $attempt->reason_code,
                    ])->all();

                return [
                    'id' => (string) $row->notification_id,
                    'tenantId' => $row->tenant_id === null ? null : (string) $row->tenant_id,
                    'category' => (string) $row->category,
                    'triggerType' => (string) $row->trigger_type,
                    'triggerId' => (string) $row->trigger_id,
                    'recipientReference' => (string) $row->recipient_reference,
                    'status' => (string) $row->status,
                    'createdAt' => (string) $row->created_at,
                    'updatedAt' => (string) $row->updated_at,
                    'attempts' => array_values($attempts),
                ];
            },
        )->all();

        return ['entries' => array_values($entries)];
    }

    private function filters(Builder $query, ?string $status, ?string $triggerType): void
    {
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($triggerType !== null) {
            $query->where('trigger_type', $triggerType);
        }
    }
}
