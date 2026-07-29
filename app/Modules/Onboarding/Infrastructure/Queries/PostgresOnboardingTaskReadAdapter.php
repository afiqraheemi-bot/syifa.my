<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Queries;

use App\Modules\Onboarding\Contracts\Tasks\OnboardingTaskReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresOnboardingTaskReadAdapter implements OnboardingTaskReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forTenant(string $tenantId): ?array
    {
        $job = $this->connection->table('onboarding_jobs')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('updated_at')
            ->first(['id', 'version']);
        if ($job === null) {
            return null;
        }

        $tasks = $this->connection->table('onboarding_tasks')
            ->where('tenant_id', $tenantId)
            ->where('onboarding_job_id', (string) $job->id)
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (object $task): array => [
                'id' => (string) $task->id,
                'title' => (string) $task->title,
                'responsibility' => (string) $task->responsibility,
                'status' => (string) $task->status,
                'mandatory' => (bool) $task->mandatory,
                'dueAt' => $task->due_at === null ? null : (string) $task->due_at,
                'evidenceReference' => $task->evidence_reference === null ? null : (string) $task->evidence_reference,
                'note' => $task->note === null ? null : (string) $task->note,
            ])
            ->all();

        return [
            'jobId' => (string) $job->id,
            'jobVersion' => (int) $job->version,
            'tasks' => array_values($tasks),
        ];
    }
}
